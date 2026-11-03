import React, { createContext, useContext, useEffect, useState } from "react";
import locales from "../data/locales.json";
import { resolveLocale, translate } from "./i18n-core.mjs";

const files = import.meta.glob("../../lang/*.json", {
    eager: true,
    import: "default",
});
const catalogs = Object.fromEntries(
    Object.entries(files).map(([path, messages]) => [
        path.split("/").pop().replace(".json", ""),
        messages,
    ]),
);
const Context = createContext(null);

export function I18nProvider({ children, initialLocale }) {
    const [locale, updateLocale] = useState(() => {
        let saved;
        try {
            saved = window.localStorage.getItem("qopenpanel.locale");
        } catch {}
        return resolveLocale(
            saved || initialLocale || navigator.language,
            locales,
        );
    });
    const metadata = locales.find((item) => item.code === locale);
    const t = (key, parameters) => translate(catalogs, locale, key, parameters);
    useEffect(() => {
        document.documentElement.lang = metadata.tag;
        document.documentElement.dir = metadata.direction || "ltr";
        try {
            window.localStorage.setItem("qopenpanel.locale", locale);
        } catch {}
        document.cookie = `qopenpanel_locale=${encodeURIComponent(locale)}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === "https:" ? "; Secure" : ""}`;
        const isDemo = !document
            .getElementById("app")
            ?.hasAttribute("data-page");
        document.title = isDemo
            ? `OpenPanel · ${t("Demonstração pública")}`
            : "OpenPanel";
        document
            .querySelector('meta[name="description"]')
            ?.setAttribute(
                "content",
                t("OpenPanel · Infraestrutura com simplicidade"),
            );
    }, [locale]);
    const setLocale = (value) => updateLocale(resolveLocale(value, locales));
    const number = (value, options = {}) =>
        new Intl.NumberFormat(metadata.tag, options).format(value);
    return (
        <Context.Provider value={{ locale, locales, t, number, setLocale }}>
            {children}
        </Context.Provider>
    );
}

export function useI18n() {
    const context = useContext(Context);
    if (!context) throw new Error("I18nProvider is required");
    return context;
}

export function LanguageSelector() {
    const { locale, locales, t, setLocale } = useI18n();
    return (
        <label className="language-selector">
            <span>{t("Idioma")}</span>
            <select
                aria-label={t("Idioma")}
                value={locale}
                onChange={(event) => setLocale(event.target.value)}
            >
                {locales.map((item) => (
                    <option key={item.code} value={item.code} lang={item.tag}>
                        {item.name}
                    </option>
                ))}
            </select>
        </label>
    );
}
