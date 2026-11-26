import assert from "node:assert/strict";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	buildIndexNowPayload,
	extractSitemapUrls,
	INDEXNOW_ENDPOINT,
	isValidIndexNowKey,
	submitIndexNow,
} from "../../scripts/submit-indexnow.mjs";
import { LANGUAGES, pages } from "../../src/config/pages";
import { baseTemplate } from "../../src/templates/base";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

const sha256Short = (filePath: string): string =>
	crypto
		.createHash("sha256")
		.update(fs.readFileSync(filePath))
		.digest("hex")
		.slice(0, 8);

test("All compiled HTML pages append deterministic 8-char SHA-256 ?v=<hash> to css/style.css and js/*.js scripts (#315)", () => {
	const trackedAssets = [
		"css/style.css",
		"js/lang-detect.js",
		"js/page-config.js",
		"js/main.js",
		"js/registry.js",
		"js/guide.js",
	];

	const expectedHashes: Record<string, string> = {};
	for (const asset of trackedAssets) {
		const distFile = path.join(DIST_DIR, asset);
		assert.ok(fs.existsSync(distFile), `Built asset must exist: dist/${asset}`);
		expectedHashes[asset] = sha256Short(distFile);
		assert.match(
			expectedHashes[asset],
			/^[0-9a-f]{8}$/,
			`Asset hash for ${asset} must be 8 lowercase hex chars`,
		);
	}

	// Verify render.tsx did NOT overwrite terser-minified dist/js/*.js with unminified public/js/*.js
	for (const jsAsset of trackedAssets.filter((a) => a.startsWith("js/"))) {
		const distSize = fs.statSync(path.join(DIST_DIR, jsAsset)).size;
		const publicSize = fs.statSync(path.join(PUBLIC_DIR, jsAsset)).size;
		assert.ok(
			distSize < publicSize,
			`dist/${jsAsset} (${distSize} B) must remain minified compared to public/${jsAsset} (${publicSize} B)`,
		);
	}

	for (const page of pages) {
		for (const lang of LANGUAGES) {
			const filename = page.filename(lang);
			const depth = filename.split("/").length - 1;
			const expectedPrefix =
				page.id === "404" ? "/" : depth === 0 ? "./" : "../".repeat(depth);
			const html = fs.readFileSync(path.join(DIST_DIR, filename), "utf-8");

			// Stylesheet versioned with matching relative prefix
			assert.ok(
				html.includes(
					`<link rel="stylesheet" href="${expectedPrefix}css/style.css?v=${expectedHashes["css/style.css"]}">`,
				),
				`${filename} must reference versioned ${expectedPrefix}css/style.css?v=${expectedHashes["css/style.css"]}`,
			);

			// Core head/body scripts versioned with matching relative prefix
			assert.ok(
				html.includes(
					`<script src="${expectedPrefix}js/lang-detect.js?v=${expectedHashes["js/lang-detect.js"]}" defer></script>`,
				),
				`${filename} must reference versioned lang-detect.js`,
			);
			assert.ok(
				html.includes(
					`<script src="${expectedPrefix}js/page-config.js?v=${expectedHashes["js/page-config.js"]}"></script>`,
				),
				`${filename} must reference versioned page-config.js`,
			);

			// Page-specific scripts versioned with matching relative prefix
			for (const script of page.scripts) {
				const hash = expectedHashes[script];
				assert.ok(hash, `Missing expected hash for ${script}`);
				assert.ok(
					html.includes(
						`<script src="${expectedPrefix}${script}?v=${hash}"></script>`,
					),
					`${filename} must reference versioned ${expectedPrefix}${script}?v=${hash}`,
				);
			}

			// Zero unversioned local css/style.css or js/*.js references
			assert.doesNotMatch(
				html,
				/href="[^"]*css\/style\.css"/,
				`${filename} must not emit unversioned css/style.css`,
			);
			assert.doesNotMatch(
				html,
				/src="[^"]*js\/[a-z0-9-]+\.js"/,
				`${filename} must not emit unversioned js/*.js scripts`,
			);
		}
	}
});

test("baseTemplate deterministically applies assetVersions across assetPrefix depths (#315)", () => {
	const renderSample = (
		assetPrefix: string,
		assetVersions: Record<string, string>,
	) =>
		baseTemplate({
			markup: "<main>Sample</main>",
			lang: "en",
			title: "Test",
			description: "Test description",
			url: "https://www.oceanviewflats.com/",
			baseUrl: "https://www.oceanviewflats.com",
			ogImage: "https://www.oceanviewflats.com/images/home-hero.webp",
			hrefLangTags: "",
			structuredData: "{}",
			assetPrefix,
			assetVersions,
			customScripts: ["js/main.js", "js/registry.js"],
			pageConfig: { pageId: "registry", lang: "en" },
		});

	const htmlV1 = renderSample("../../", {
		"css/style.css": "11111111",
		"js/lang-detect.js": "22222222",
		"js/page-config.js": "33333333",
		"js/main.js": "44444444",
		"js/registry.js": "55555555",
	});
	const htmlV2 = renderSample("../../", {
		"css/style.css": "aaaaaaaa",
		"js/lang-detect.js": "22222222",
		"js/page-config.js": "33333333",
		"js/main.js": "bbbbbbbb",
		"js/registry.js": "55555555",
	});

	assert.ok(htmlV1.includes('href="../../css/style.css?v=11111111"'));
	assert.ok(htmlV1.includes('src="../../js/lang-detect.js?v=22222222" defer'));
	assert.ok(htmlV1.includes('src="../../js/page-config.js?v=33333333"'));
	assert.ok(htmlV1.includes('src="../../js/main.js?v=44444444"'));
	assert.ok(htmlV1.includes('src="../../js/registry.js?v=55555555"'));

	assert.ok(htmlV2.includes('href="../../css/style.css?v=aaaaaaaa"'));
	assert.ok(htmlV2.includes('src="../../js/main.js?v=bbbbbbbb"'));
});

test("Apache .htaccess enables mod_deflate, mod_expires, tiered Cache-Control inside single mod_headers block, and strict /api/* no-store (#315)", () => {
	const publicHtaccess = fs.readFileSync(
		path.join(PUBLIC_DIR, ".htaccess"),
		"utf-8",
	);
	const distHtaccess = fs.readFileSync(
		path.join(DIST_DIR, ".htaccess"),
		"utf-8",
	);
	assert.equal(
		distHtaccess,
		publicHtaccess,
		"dist/.htaccess must match public/.htaccess",
	);

	const apiHtaccess = fs.readFileSync(
		path.join(PUBLIC_DIR, "api", ".htaccess"),
		"utf-8",
	);
	const distApiHtaccess = fs.readFileSync(
		path.join(DIST_DIR, "api", ".htaccess"),
		"utf-8",
	);
	assert.equal(
		distApiHtaccess,
		apiHtaccess,
		"dist/api/.htaccess must match public/api/.htaccess",
	);

	// 1. Exactly one <IfModule mod_headers.c> block containing security headers + tiered Cache-Control
	const headersBlocks = publicHtaccess.match(
		/<IfModule\s+mod_headers\.c>([\s\S]*?)<\/IfModule>/gi,
	);
	assert.equal(
		headersBlocks?.length,
		1,
		"public/.htaccess must contain exactly one <IfModule mod_headers.c> block",
	);
	const headersBlock = headersBlocks[0];

	// Preserve existing security headers
	for (const headerName of [
		"Strict-Transport-Security",
		"X-Frame-Options",
		"X-Content-Type-Options",
		"Referrer-Policy",
		"Permissions-Policy",
		"Content-Security-Policy-Report-Only",
	]) {
		assert.match(
			headersBlock,
			new RegExp(`^\\s*Header\\s+always\\s+set\\s+${headerName}\\b`, "im"),
			`public/.htaccess must preserve Header always set ${headerName}`,
		);
	}

	// Tiered <FilesMatch> Cache-Control directives
	assert.match(
		headersBlock,
		/<FilesMatch\s+"\\\.\(webp\|png\|jpg\|jpeg\|svg\|ico\|woff2\)\$">[\s\S]*?Header\s+set\s+Cache-Control\s+"public,\s*max-age=2592000"[\s\S]*?<\/FilesMatch>/i,
		"Images and woff2 fonts must be cached for 30 days (2592000s)",
	);
	assert.match(
		headersBlock,
		/<FilesMatch\s+"\\\.\(css\|js\)\$">[\s\S]*?Header\s+set\s+Cache-Control\s+"public,\s*max-age=604800"[\s\S]*?<\/FilesMatch>/i,
		"Versioned CSS and JS must be cached for 7 days (604800s)",
	);
	assert.match(
		headersBlock,
		/<FilesMatch\s+"\\\.\(html\|xml\|txt\|webmanifest\)\$">[\s\S]*?Header\s+set\s+Cache-Control\s+"public,\s*max-age=300,\s*must-revalidate"[\s\S]*?<\/FilesMatch>/i,
		"HTML, XML, TXT, and webmanifest must be cached for 5 minutes with must-revalidate",
	);
	assert.match(
		headersBlock,
		/<FilesMatch\s+"\\\.php\$">[\s\S]*?Header\s+always\s+set\s+Cache-Control\s+"no-store,\s*no-cache,\s*must-revalidate"[\s\S]*?<\/FilesMatch>/i,
		"PHP responses must explicitly enforce Header always set Cache-Control no-store, no-cache, must-revalidate (same table as public/api/.htaccess)",
	);

	// 2. <IfModule mod_deflate.c> compresses all required MIME types
	const deflateMatch = publicHtaccess.match(
		/<IfModule\s+mod_deflate\.c>([\s\S]*?)<\/IfModule>/i,
	);
	assert.ok(
		deflateMatch,
		"public/.htaccess must define <IfModule mod_deflate.c>",
	);
	const deflateBlock = deflateMatch[1];
	const deflateMimes = new Set(
		deflateBlock
			.split(/\r?\n/)
			.map((line) => line.trim())
			.filter((line) => /^AddOutputFilterByType\s+DEFLATE\s+/i.test(line))
			.flatMap((line) => line.split(/\s+/).slice(2)),
	);
	for (const mime of [
		"text/html",
		"text/plain",
		"text/xml",
		"text/css",
		"application/javascript",
		"application/json",
		"application/xml",
		"image/svg+xml",
	]) {
		assert.ok(deflateMimes.has(mime), `mod_deflate must compress ${mime}`);
	}

	// 3. <IfModule mod_expires.c> defines tiered expiry rules
	const expiresMatch = publicHtaccess.match(
		/<IfModule\s+mod_expires\.c>([\s\S]*?)<\/IfModule>/i,
	);
	assert.ok(
		expiresMatch,
		"public/.htaccess must define <IfModule mod_expires.c>",
	);
	const expiresBlock = expiresMatch[1];
	assert.match(expiresBlock, /^\s*ExpiresActive\s+On\s*$/im);
	assert.match(
		expiresBlock,
		/ExpiresByType\s+image\/webp\s+"access plus 30 days"/i,
	);
	assert.match(
		expiresBlock,
		/ExpiresByType\s+font\/woff2\s+"access plus 30 days"/i,
	);
	assert.match(
		expiresBlock,
		/ExpiresByType\s+text\/css\s+"access plus 7 days"/i,
	);
	assert.match(
		expiresBlock,
		/ExpiresByType\s+application\/javascript\s+"access plus 7 days"/i,
	);
	assert.match(
		expiresBlock,
		/ExpiresByType\s+text\/html\s+"access plus 5 minutes"/i,
	);

	// 4. public/api/.htaccess preserves strict non-caching for /api/*
	assert.match(
		apiHtaccess,
		/<IfModule\s+mod_expires\.c>[\s\S]*?ExpiresActive\s+Off[\s\S]*?<\/IfModule>/i,
		"public/api/.htaccess must disable mod_expires",
	);
	assert.match(
		apiHtaccess,
		/<IfModule\s+mod_headers\.c>[\s\S]*?Header\s+always\s+set\s+Cache-Control\s+"no-store,\s*no-cache,\s*must-revalidate"[\s\S]*?<\/IfModule>/i,
		"public/api/.htaccess must enforce Header always set Cache-Control no-store, no-cache, must-revalidate",
	);

	// 5. Preserve CVE hardening and canonical redirects
	assert.match(publicHtaccess, /^\s*LimitRequestBody\s+65536\s*$/im);
	assert.match(
		publicHtaccess,
		/RewriteCond\s+%\{CONTENT_TYPE\}\s+\^multipart\/form-data/i,
	);
	assert.match(publicHtaccess, /\[R=301,L\]/);
});

test("IndexNow helper extracts sitemap <loc> URLs, validates keys, builds JSON payloads, and handles non-blocking submission (#315)", async () => {
	// 1. Key validation
	assert.equal(isValidIndexNowKey("sample-indexnow-key-2026"), true);
	assert.equal(isValidIndexNowKey("short"), false);
	assert.equal(isValidIndexNowKey("../traversal-key"), false);
	assert.equal(isValidIndexNowKey("has space key"), false);

	// 2. Sitemap URL extraction against generated dist/sitemap.xml
	const sitemapPath = path.join(DIST_DIR, "sitemap.xml");
	const sitemapXml = fs.readFileSync(sitemapPath, "utf-8");
	const distUrls = extractSitemapUrls(sitemapXml);
	const expectedPublicCount =
		pages.filter((p) => !p.noIndex).length * LANGUAGES.length;
	assert.equal(
		distUrls.length,
		expectedPublicCount,
		"extractSitemapUrls must extract every public URL from dist/sitemap.xml",
	);
	for (const url of distUrls) {
		assert.ok(
			url.startsWith("https://www.oceanviewflats.com/"),
			`Extracted URL must belong to canonical host: ${url}`,
		);
	}

	// Synthetic sitemap deduplication & trimming
	const syntheticUrls = extractSitemapUrls(`
		<urlset>
			<url><loc>  https://www.oceanviewflats.com/  </loc></url>
			<url><loc>https://www.oceanviewflats.com/</loc></url>
			<url><loc>https://www.oceanviewflats.com/contact/</loc></url>
		</urlset>
	`);
	assert.deepEqual(syntheticUrls, [
		"https://www.oceanviewflats.com/",
		"https://www.oceanviewflats.com/contact/",
	]);
	assert.deepEqual(extractSitemapUrls(""), []);

	// 3. Payload assembly filters foreign hosts & validates key
	const testKey = "sample-indexnow-key-2026";
	const payload = buildIndexNowPayload({
		key: testKey,
		urlList: [
			"https://www.oceanviewflats.com/",
			"https://www.oceanviewflats.com/Oceanview1707/",
			"https://www.oceanviewflats.com/",
			"https://foreign.example.com/ignored/",
			"not-a-valid-url",
		],
	});
	assert.deepEqual(payload, {
		host: "www.oceanviewflats.com",
		key: testKey,
		keyLocation: `https://www.oceanviewflats.com/${testKey}.txt`,
		urlList: [
			"https://www.oceanviewflats.com/",
			"https://www.oceanviewflats.com/Oceanview1707/",
		],
	});

	assert.throws(
		() => buildIndexNowPayload({ key: "../bad", urlList: distUrls }),
		/Invalid IndexNow key/,
	);

	// 4. submitIndexNow skips cleanly when key is unset or invalid without network calls
	let fetchCalls = 0;
	const countingFetch = async () => {
		fetchCalls += 1;
		return new Response("OK", { status: 200 });
	};

	const skippedMissing = await submitIndexNow({
		key: "",
		sitemapPath,
		fetchImpl: countingFetch,
	});
	assert.deepEqual(skippedMissing, { skipped: true, reason: "missing_key" });
	assert.equal(fetchCalls, 0);

	const skippedInvalid = await submitIndexNow({
		key: "bad/key!",
		sitemapPath,
		fetchImpl: countingFetch,
	});
	assert.deepEqual(skippedInvalid, { skipped: true, reason: "invalid_key" });
	assert.equal(fetchCalls, 0);

	// 5. submitIndexNow POSTs valid JSON payload to mocked endpoint
	let capturedUrl = "";
	let capturedInit: RequestInit | undefined;
	const okResult = await submitIndexNow({
		key: testKey,
		sitemapPath,
		fetchImpl: async (url: string | URL | Request, init?: RequestInit) => {
			capturedUrl = String(url);
			capturedInit = init;
			return new Response("", { status: 200 });
		},
	});
	assert.deepEqual(okResult, {
		submitted: true,
		status: 200,
		count: expectedPublicCount,
	});
	assert.equal(capturedUrl, INDEXNOW_ENDPOINT);
	assert.equal(capturedInit?.method, "POST");
	const sentPayload = JSON.parse(String(capturedInit?.body));
	assert.equal(sentPayload.host, "www.oceanviewflats.com");
	assert.equal(sentPayload.key, testKey);
	assert.equal(
		sentPayload.keyLocation,
		`https://www.oceanviewflats.com/${testKey}.txt`,
	);
	assert.equal(sentPayload.urlList.length, expectedPublicCount);

	// 6. Non-blocking behavior on remote HTTP error & network failure
	const httpErrResult = await submitIndexNow({
		key: testKey,
		sitemapPath,
		fetchImpl: async () => new Response("Rate limited", { status: 429 }),
	});
	assert.deepEqual(httpErrResult, {
		submitted: false,
		status: 429,
		count: expectedPublicCount,
	});

	const networkErrResult = await submitIndexNow({
		key: testKey,
		sitemapPath,
		fetchImpl: async () => {
			throw new Error("socket hang up");
		},
	});
	assert.equal(networkErrResult.submitted, false);
	assert.equal(networkErrResult.error, "socket hang up");
});

test("deploy.yml safely emits dist/<INDEXNOW_KEY>.txt before SFTP and triggers non-blocking IndexNow ping after deploy (#315)", () => {
	const deployYml = fs.readFileSync(
		path.join(process.cwd(), ".github", "workflows", "deploy.yml"),
		"utf-8",
	);

	// Never interpolate vars.INDEXNOW_KEY directly inside any step's run: script (zizmor guardrail)
	const steps = deployYml.split(/^\s*-\s+name:/m).slice(1);
	for (const step of steps) {
		const runIdx = step.search(/^\s+run:/m);
		if (runIdx === -1) {
			continue;
		}
		const runSection = step.slice(runIdx);
		assert.doesNotMatch(
			runSection,
			/\$\{\{\s*vars\.INDEXNOW_KEY\s*\}\}/,
			"deploy.yml must pass vars.INDEXNOW_KEY via env:, not directly inside run:",
		);
	}

	// Must bind INDEXNOW_KEY via env: twice (key file step + post-deploy ping step)
	const envBindings = deployYml.match(
		/INDEXNOW_KEY:\s*\$\{\{\s*vars\.INDEXNOW_KEY\s*\}\}/g,
	);
	assert.equal(
		envBindings?.length,
		2,
		"deploy.yml must bind INDEXNOW_KEY from vars.INDEXNOW_KEY via env: for both key emission and post-deploy ping",
	);

	const emitKeyIdx = deployYml.indexOf(
		"- name: Emit IndexNow Verification Key File",
	);
	const deploySftpIdx = deployYml.indexOf("- name: Deploy to SFTP");
	const notifyIdx = deployYml.indexOf("- name: Notify IndexNow Search Engines");

	assert.ok(
		emitKeyIdx > 0,
		"Emit IndexNow Verification Key File step must exist",
	);
	assert.ok(
		emitKeyIdx < deploySftpIdx,
		"IndexNow key file must be written into dist/ before Deploy to SFTP",
	);
	assert.ok(
		notifyIdx > deploySftpIdx,
		"IndexNow submission must run after Deploy to SFTP",
	);

	assert.ok(
		deployYml.includes("^[A-Za-z0-9-]{8,128}$"),
		"deploy.yml must validate INDEXNOW_KEY format before writing the verification file",
	);
	assert.match(
		deployYml,
		/dist\/\$\{INDEXNOW_KEY\}\.txt"/,
		"deploy.yml must write the IndexNow verification key file in dist/ using shell env expansion",
	);

	const notifySection = deployYml.slice(notifyIdx);
	assert.match(
		notifySection,
		/continue-on-error:\s*true/,
		"IndexNow post-deploy ping step must set continue-on-error: true",
	);
	assert.ok(
		notifySection.includes("node scripts/submit-indexnow.mjs"),
		"IndexNow post-deploy ping step must invoke node scripts/submit-indexnow.mjs",
	);
});
