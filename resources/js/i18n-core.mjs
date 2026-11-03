export function resolveLocale(value, locales, fallback = "pt_BR") {
    if (typeof value !== "string") return fallback;
    const normalized = value.replaceAll("_", "-").toLowerCase();
    return (
        locales.find(
            (locale) =>
                locale.code.toLowerCase() === value.toLowerCase() ||
                locale.tag.toLowerCase() === normalized,
        )?.code ??
        locales.find(
            (locale) =>
                locale.tag.toLowerCase().split("-")[0] ===
                normalized.split("-")[0],
        )?.code ??
        fallback
    );
}

export function translate(catalogs, locale, key, parameters = {}) {
    if (typeof key !== "string") return "";
    const localized =
        Object.hasOwn(catalogs, locale) && Object.hasOwn(catalogs[locale], key)
            ? catalogs[locale][key]
            : null;
    const fallback = Object.hasOwn(catalogs.pt_BR ?? {}, key)
        ? catalogs.pt_BR[key]
        : null;
    const text =
        (typeof localized === "string" && localized) ||
        (typeof fallback === "string" && fallback) ||
        key;
    return text.replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, name) =>
        Object.hasOwn(parameters, name) ? String(parameters[name]) : match,
    );
}
