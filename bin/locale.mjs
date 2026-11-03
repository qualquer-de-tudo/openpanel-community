import fs from "node:fs";
import { resolveLocale, translate } from "../resources/js/i18n-core.mjs";
const locales = JSON.parse(
    fs.readFileSync(
        new URL("../resources/data/locales.json", import.meta.url),
        "utf8",
    ),
);
const locale = resolveLocale(
    process.env.QOPENPANEL_LOCALE || process.env.LANG,
    locales,
);
const catalogs = {
    pt_BR: JSON.parse(
        fs.readFileSync(new URL("../lang/pt_BR.json", import.meta.url), "utf8"),
    ),
};
catalogs[locale] = JSON.parse(
    fs.readFileSync(new URL(`../lang/${locale}.json`, import.meta.url), "utf8"),
);
export const languageTag = locales.find((item) => item.code === locale).tag;
export const t = (key, parameters) =>
    translate(catalogs, locale, key, parameters);
