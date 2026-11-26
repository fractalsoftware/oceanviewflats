import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const INDEXNOW_ENDPOINT = "https://api.indexnow.org/indexnow";
export const DEFAULT_BASE_URL = "https://www.oceanviewflats.com";
export const INDEXNOW_KEY_PATTERN = /^[A-Za-z0-9-]{8,128}$/;

export function isValidIndexNowKey(key) {
	return typeof key === "string" && INDEXNOW_KEY_PATTERN.test(key.trim());
}

export function extractSitemapUrls(sitemapXml) {
	if (typeof sitemapXml !== "string" || sitemapXml.trim() === "") {
		return [];
	}

	const urls = [];
	const seen = new Set();
	const locPattern = /<loc>([^<]+)<\/loc>/gi;

	for (const match of sitemapXml.matchAll(locPattern)) {
		const url = match[1].trim();
		if (url.length > 0 && !seen.has(url)) {
			seen.add(url);
			urls.push(url);
		}
	}

	return urls;
}

export function buildIndexNowPayload({
	key,
	urlList,
	baseUrl = DEFAULT_BASE_URL,
}) {
	const trimmedKey = typeof key === "string" ? key.trim() : "";
	if (!isValidIndexNowKey(trimmedKey)) {
		throw new Error(
			"Invalid IndexNow key: must match /^[A-Za-z0-9-]{8,128}$/.",
		);
	}

	const normalizedBase = baseUrl.replace(/\/+$/, "");
	const parsedBase = new URL(normalizedBase);
	const host = parsedBase.host;
	const keyLocation = `${normalizedBase}/${trimmedKey}.txt`;

	const filteredUrls = [];
	const seen = new Set();
	for (const rawUrl of Array.isArray(urlList) ? urlList : []) {
		if (typeof rawUrl !== "string") {
			continue;
		}
		const trimmedUrl = rawUrl.trim();
		if (!trimmedUrl || seen.has(trimmedUrl)) {
			continue;
		}
		try {
			const parsed = new URL(trimmedUrl);
			if (parsed.host === host) {
				seen.add(trimmedUrl);
				filteredUrls.push(trimmedUrl);
			}
		} catch {
			// Skip malformed URLs
		}
	}

	return {
		host,
		key: trimmedKey,
		keyLocation,
		urlList: filteredUrls,
	};
}

export async function submitIndexNow({
	key = process.env.INDEXNOW_KEY ?? "",
	sitemapPath = path.join(process.cwd(), "dist", "sitemap.xml"),
	baseUrl = DEFAULT_BASE_URL,
	endpoint = INDEXNOW_ENDPOINT,
	fetchImpl = globalThis.fetch,
	dryRun = false,
} = {}) {
	const trimmedKey = typeof key === "string" ? key.trim() : "";
	if (!trimmedKey) {
		console.log("INDEXNOW_KEY is not set; skipping IndexNow submission.");
		return { skipped: true, reason: "missing_key" };
	}

	if (!isValidIndexNowKey(trimmedKey)) {
		console.warn(
			"INDEXNOW_KEY does not match ^[A-Za-z0-9-]{8,128}$; skipping IndexNow submission.",
		);
		return { skipped: true, reason: "invalid_key" };
	}

	if (!fs.existsSync(sitemapPath)) {
		console.warn(
			`Sitemap not found at ${sitemapPath}; skipping IndexNow submission.`,
		);
		return { skipped: true, reason: "missing_sitemap" };
	}

	const sitemapXml = fs.readFileSync(sitemapPath, "utf-8");
	const extractedUrls = extractSitemapUrls(sitemapXml);
	if (extractedUrls.length === 0) {
		console.warn(
			"No <loc> URLs found in sitemap; skipping IndexNow submission.",
		);
		return { skipped: true, reason: "empty_sitemap" };
	}

	const payload = buildIndexNowPayload({
		key: trimmedKey,
		urlList: extractedUrls,
		baseUrl,
	});

	if (payload.urlList.length === 0) {
		console.warn(
			`No sitemap URLs matched host ${payload.host}; skipping IndexNow submission.`,
		);
		return { skipped: true, reason: "no_matching_host_urls" };
	}

	if (dryRun) {
		console.log(
			`IndexNow dry run: prepared ${payload.urlList.length} URLs for ${payload.host}.`,
		);
		return { submitted: false, dryRun: true, payload };
	}

	try {
		const response = await fetchImpl(endpoint, {
			method: "POST",
			headers: {
				"Content-Type": "application/json; charset=utf-8",
			},
			body: JSON.stringify(payload),
			signal: AbortSignal.timeout(15000),
		});

		if (!response.ok) {
			const responseText = await response.text().catch(() => "");
			console.warn(
				`IndexNow submission returned HTTP ${response.status}${responseText ? `: ${responseText}` : ""}`,
			);
			return {
				submitted: false,
				status: response.status,
				count: payload.urlList.length,
			};
		}

		console.log(
			`Submitted ${payload.urlList.length} URLs to IndexNow (HTTP ${response.status}).`,
		);
		return {
			submitted: true,
			status: response.status,
			count: payload.urlList.length,
		};
	} catch (error) {
		const message = error instanceof Error ? error.message : String(error);
		console.warn(`IndexNow submission failed (non-blocking): ${message}`);
		return {
			submitted: false,
			error: message,
			count: payload.urlList.length,
		};
	}
}

const isMainModule =
	Boolean(process.argv[1]) &&
	path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isMainModule) {
	const key = process.env.INDEXNOW_KEY ?? "";
	const sitemapPath =
		process.env.INDEXNOW_SITEMAP_PATH ??
		path.join(process.cwd(), "dist", "sitemap.xml");
	const baseUrl = process.env.INDEXNOW_BASE_URL ?? DEFAULT_BASE_URL;
	const dryRun = process.env.INDEXNOW_DRY_RUN === "1";

	submitIndexNow({ key, sitemapPath, baseUrl, dryRun }).catch((error) => {
		const message = error instanceof Error ? error.message : String(error);
		console.warn(`IndexNow submission failed (non-blocking): ${message}`);
	});
}
