import { ChevronRight } from "lucide-react";
import React from "react";
import { getHomePagePath } from "../constants/config";
import { BREADCRUMB_THEME, type BreadcrumbSurface } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export interface BreadcrumbsProps {
	lang: Lang;
	assetPrefix?: string;
	currentLabel: string;
	currentHref: string;
	surface?: BreadcrumbSurface;
}

export const Breadcrumbs = ({
	lang,
	assetPrefix = "../",
	currentLabel,
	currentHref,
	surface = "inline",
}: BreadcrumbsProps) => {
	const t = dict[lang];
	const styles = BREADCRUMB_THEME[surface];
	const homeHref = getHomePagePath(lang, assetPrefix);

	return (
		<nav aria-label={t.breadcrumbLabel} className={styles.nav}>
			<div className={styles.container}>
				<ol className={styles.list}>
					<li className={styles.item}>
						<a href={homeHref} className={styles.link}>
							{t.breadcrumbHome}
						</a>
					</li>
					<li className={styles.item}>
						<ChevronRight className={styles.separator} aria-hidden="true" />
						<a
							href={currentHref}
							aria-current="page"
							className={styles.current}
						>
							{currentLabel}
						</a>
					</li>
				</ol>
			</div>
		</nav>
	);
};
