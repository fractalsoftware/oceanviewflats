interface TemplateProps {
	markup: string;
	lang: "en" | "es" | "fr" | "it" | "de" | "ja";
	title: string;
	description: string;
	url: string;
	baseUrl: string;
	ogImage: string;
	hrefLangTags: string;
	structuredData: string;
	assetPrefix: string;
	customScripts?: string[];
	pageConfig?: Record<string, any>;
}

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
	hrefLangTags,
	structuredData,
	assetPrefix,
	customScripts = [],
	pageConfig,
}: TemplateProps) => {
	const scripts: string[] = [];
	if (pageConfig) {
		scripts.push(
			`<script id="ovf-page-config" type="application/json">${JSON.stringify(pageConfig).replace(/</g, "\\u003c")}</script>`,
		);
		scripts.push(`<script src="${assetPrefix}js/page-config.js"></script>`);
	}
	for (const src of customScripts) {
		if (
			pageConfig &&
			(src === "js/page-config.js" || src === "page-config.js")
		) {
			continue;
		}
		scripts.push(`<script src="${assetPrefix}${src}"></script>`);
	}
	const scriptsMarkup =
		scripts.length > 0 ? `\n    ${scripts.join("\n    ")}` : "";

	return `<!DOCTYPE html>
<html lang="${lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <meta name="description" content="${description}">

    <!-- favicons -->
    <link rel="icon" type="image/svg+xml" href="${assetPrefix}favicon.svg?v=20260614">
    <link rel="icon" type="image/png" href="${assetPrefix}favicon-96x96.png?v=20260614" sizes="96x96" />
    <link rel="shortcut icon" href="${assetPrefix}favicon.ico?v=20260614" />
    <link rel="apple-touch-icon" sizes="180x180" href="${assetPrefix}apple-touch-icon.png?v=20260614" />
    <meta name="apple-mobile-web-app-title" content="${description}" />
    <link rel="manifest" href="${assetPrefix}site.webmanifest?v=20260614" />
    
    <!-- Open Graph / Social Sharing -->
    <meta property="og:site_name" content="OceanViewFlats">
    <meta property="og:type" content="website">
    <meta property="og:title" content="${title}">
    <meta property="og:description" content="${description}">
    <meta property="og:image" content="${ogImage}">
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
    <meta name="twitter:image" content="${ogImage}">

    <script src="${assetPrefix}js/lang-detect.js"></script>

    <!-- Multilingual SEO -->
${hrefLangTags}

    <!-- Structured Data (JSON-LD) for Generative Engines & Rich Snippets -->
    <script type="application/ld+json">
        ${structuredData}
    </script>

    <link rel="stylesheet" href="${assetPrefix}css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
