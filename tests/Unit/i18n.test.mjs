import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { resolveLocale, translate } from "../../resources/js/i18n-core.mjs";

const locales = JSON.parse(fs.readFileSync("resources/data/locales.json"));
const catalogs = Object.fromEntries(
    locales.map((locale) => [
        locale.code,
        JSON.parse(fs.readFileSync(`lang/${locale.code}.json`)),
    ]),
);

test("all six initial languages translate install and navigation", () => {
    const expected = {
        pt_BR: ["Instalar", "Visão geral"],
        en: ["Install", "Overview"],
        fr: ["Installer", "Vue d’ensemble"],
        es: ["Instalar", "Vista general"],
        zh_CN: ["安装", "概览"],
        ja: ["インストール", "概要"],
    };
    for (const [locale, [install, overview]] of Object.entries(expected)) {
        assert.equal(translate(catalogs, locale, "Instalar"), install);
        assert.equal(translate(catalogs, locale, "Visão geral"), overview);
    }
});

test("locale negotiation handles regional tags and rejects unsupported input", () => {
    assert.equal(resolveLocale("pt-PT", locales), "pt_BR");
    assert.equal(resolveLocale("en-US", locales), "en");
    assert.equal(resolveLocale("zh-CN", locales), "zh_CN");
    assert.equal(resolveLocale("ja-JP", locales), "ja");
    assert.equal(resolveLocale("../../private", locales), "pt_BR");
    assert.equal(resolveLocale(null, locales), "pt_BR");
});

test("interpolation preserves user values literally and has a safe fallback", () => {
    assert.equal(
        translate(catalogs, "en", "Remover :name", {
            name: "$& <script>:other</script>",
        }),
        "Remove $& <script>:other</script>",
    );
    assert.equal(translate(catalogs, "missing", "Instalar"), "Instalar");
    assert.equal(translate(catalogs, "en", "constructor"), "constructor");
    assert.equal(translate(catalogs, "en", undefined), "");
});
