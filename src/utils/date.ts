import type { Lang } from "../types";

const DATE_LOCALES: Record<Lang, string> = {
	en: "en-US",
	es: "es-CO",
	fr: "fr-FR",
	it: "it-IT",
	de: "de-DE",
	ja: "ja-JP",
};

export const formatDate = (date: Date) => {
	const y = date.getFullYear();
	const m = String(date.getMonth() + 1).padStart(2, "0");
	const d = String(date.getDate()).padStart(2, "0");
	return `${y}-${m}-${d}`;
};

export const formatLocalizedIsoDate = (isoDate: string, lang: Lang): string => {
	const parsed = new Date(`${isoDate}T00:00:00Z`);
	if (Number.isNaN(parsed.getTime())) return isoDate;
	return new Intl.DateTimeFormat(DATE_LOCALES[lang], {
		year: "numeric",
		month: "long",
		day: "numeric",
		timeZone: "UTC",
	}).format(parsed);
};
