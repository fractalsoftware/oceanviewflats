export interface OgImageMeta {
	url: string;
	width?: number;
	height?: number;
	alt?: string;
}

interface TemplateProps {
	markup: string;
	lang: "en" | "es" | "fr" | "it" | "de" | "ja";
	title: string;
	description: string;
	url: string;
	baseUrl: string;
	ogImage: OgImageMeta | string;
	heroPreloadHref?: string;
	heroPreloadSrcSet?: string;
	heroPreloadSizes?: string;
	hrefLangTags: string;
	structuredData: string;
	assetPrefix: string;
	assetVersions?: Record<string, string>;
	noIndex?: boolean;
	customScripts?: string[];
	pageConfig?: Record<string, any>;
}

const withAssetVersion = (
	assetPath: string,
	assetVersions?: Record<string, string>,
): string => {
	const normalized = assetPath.replace(/^\/+/, "");
	const version = assetVersions?.[normalized];
	if (!version || normalized.includes("?")) {
		return assetPath;
	}
	return `${assetPath}?v=${version}`;
};

const escapeHtmlAttr = (value: string): string =>
	value
		.replace(/&/g, "&amp;")
		.replace(/"/g, "&quot;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;");

const LOCALE_MAP = {
	en: "en_US",
	es: "es_ES",
	fr: "fr_FR",
	it: "it_IT",
	de: "de_DE",
	ja: "ja_JP",
};

export const baseTemplate = ({
	markup,
	lang,
	title,
	description,
	url,
	baseUrl,
	ogImage,
	heroPreloadHref,
	heroPreloadSrcSet,
	heroPreloadSizes,
	hrefLangTags,
	structuredData,
	assetPrefix,
	assetVersions,
	noIndex = false,
	customScripts = [],
	pageConfig,
}: TemplateProps) => {
	const robotsMetaTag = noIndex
		? `\n    <meta name="robots" content="noindex, nofollow" />`
		: "";
	const hrefLangMarkup =
		!noIndex && hrefLangTags
			? `\n\n    <!-- Multilingual SEO -->\n${hrefLangTags}`
			: "";
	const scripts: string[] = [];
	if (pageConfig) {
		scripts.push(
			`<script id="ovf-page-config" type="application/json">${JSON.stringify(pageConfig).replace(/</g, "\\u003c")}</script>`,
		);
		scripts.push(
			`<script src="${assetPrefix}${withAssetVersion("js/page-config.js", assetVersions)}"></script>`,
		);
	}
	for (const src of customScripts) {
		if (
			pageConfig &&
			(src === "js/page-config.js" || src === "page-config.js")
		) {
			continue;
		}
		scripts.push(
			`<script src="${assetPrefix}${withAssetVersion(src, assetVersions)}"></script>`,
		);
	}
	const scriptsMarkup =
		scripts.length > 0 ? `\n    ${scripts.join("\n    ")}` : "";

	const resolvedOgImage: OgImageMeta =
		typeof ogImage === "string" ? { url: ogImage } : ogImage;
	const escapedOgImageUrl = escapeHtmlAttr(resolvedOgImage.url);
	const escapedOgImageAlt = resolvedOgImage.alt
		? escapeHtmlAttr(resolvedOgImage.alt)
		: "";
	const ogImageMetaLines: string[] = [
		`<meta property="og:image" content="${escapedOgImageUrl}">`,
	];
	if (resolvedOgImage.width) {
		ogImageMetaLines.push(
			`<meta property="og:image:width" content="${resolvedOgImage.width}">`,
		);
	}
	if (resolvedOgImage.height) {
		ogImageMetaLines.push(
			`<meta property="og:image:height" content="${resolvedOgImage.height}">`,
		);
	}
	if (escapedOgImageAlt) {
		ogImageMetaLines.push(
			`<meta property="og:image:alt" content="${escapedOgImageAlt}">`,
		);
	}
	const ogImageMetaMarkup = ogImageMetaLines.join("\n    ");
	const twitterImageAltMarkup = escapedOgImageAlt
		? `\n    <meta name="twitter:image:alt" content="${escapedOgImageAlt}">`
		: "";
	const heroPreloadMarkup = heroPreloadHref
		? (() => {
				const attrs = [
					'rel="preload"',
					'as="image"',
					`href="${escapeHtmlAttr(heroPreloadHref)}"`,
				];
				if (heroPreloadSrcSet) {
					attrs.push(`imagesrcset="${escapeHtmlAttr(heroPreloadSrcSet)}"`);
					if (heroPreloadSizes) {
						attrs.push(`imagesizes="${escapeHtmlAttr(heroPreloadSizes)}"`);
					}
				}
				attrs.push('fetchpriority="high"');
				return `\n    <link ${attrs.join(" ")} />`;
			})()
		: "";

	return `<!DOCTYPE html>
<html lang="${lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <meta name="description" content="${description}">${robotsMetaTag}

    <!-- favicons -->
    <link rel="icon" type="image/svg+xml" href="${assetPrefix}favicon.svg?v=20260614">
    <link rel="icon" type="image/png" href="${assetPrefix}favicon-96x96.png?v=20260614" sizes="96x96" />
    <link rel="shortcut icon" href="${assetPrefix}favicon.ico?v=20260614" />
    <link rel="apple-touch-icon" sizes="180x180" href="${assetPrefix}apple-touch-icon.png?v=20260614" />
    <meta name="apple-mobile-web-app-title" content="OceanViewFlats" />
    <link rel="manifest" href="${assetPrefix}site.webmanifest?v=20260614" />
    
    <!-- Open Graph / Social Sharing -->
    <meta property="og:site_name" content="OceanViewFlats">
    <meta property="og:type" content="website">
    <meta property="og:title" content="${title}">
    <meta property="og:description" content="${description}">
    ${ogImageMetaMarkup}
    <meta property="og:url" content="${url}">
    <meta property="og:locale" content="${LOCALE_MAP[lang]}">
    ${Object.keys(LOCALE_MAP)
			.filter((l) => l !== lang)
			.map(
				(l) =>
					`<meta property="og:locale:alternate" content="${LOCALE_MAP[l as keyof typeof LOCALE_MAP]}">`,
			)
			.join("\n    ")}
    <link rel="canonical" href="${url}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="${title}">
    <meta name="twitter:description" content="${description}">
    <meta name="twitter:image" content="${escapedOgImageUrl}">${twitterImageAltMarkup}

    <script src="${assetPrefix}${withAssetVersion("js/lang-detect.js", assetVersions)}" defer></script>${hrefLangMarkup}

    <!-- Structured Data (JSON-LD) for Generative Engines & Rich Snippets -->
    <script type="application/ld+json">
        ${structuredData}
    </script>${heroPreloadMarkup}
    <link rel="preload" as="font" type="font/woff2" href="${assetPrefix}fonts/inter-var.woff2" crossorigin />
    <link rel="stylesheet" href="${assetPrefix}${withAssetVersion("css/style.css", assetVersions)}">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scroll-smooth { scroll-behavior: smooth; }
    </style>
</head>
<body>
    <div id="root">${markup}</div>${scriptsMarkup}
</body>
</html>`;
};
