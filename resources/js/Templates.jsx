import { panelUrl } from "./panel-url.mjs";
import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import {
    Layers,
    Plus,
    Download,
    GitBranch,
    X,
    ArrowUpRight,
} from "lucide-react";
import { useI18n } from "./i18n.jsx";
const newer = (left, right) =>
    left.localeCompare(right, undefined, { numeric: true });
export default function Templates({
    templates = [],
    installations = [],
    demo = false,
}) {
    const { t, locale, number } = useI18n();
    const [search, setSearch] = useState(""),
        [selected, setSelected] = useState(null),
        [targets, setTargets] = useState({}),
        [restore, setRestore] = useState({});
    const form = useForm({
        template_id: "",
        version: "",
        name: "",
        domain: "",
    });
    const mutation = useForm({});
    const text = (value) => value?.[locale] || value?.en || value?.pt_BR || "";
    const grouped = Object.values(
        templates.reduce((groups, item) => {
            (groups[item.id] ??= []).push(item);
            return groups;
        }, {}),
    ).map((versions) => versions.sort((a, b) => newer(b.version, a.version)));
    const choose = (versions) => {
        const release = versions.find((item) => item.compatible) || versions[0];
        setSelected(versions);
        form.clearErrors();
        form.setData({
            template_id: release.id,
            version: release.version,
            name: "",
            domain: "",
        });
    };
    const active = selected?.find((item) => item.version === form.data.version);
    const submit = (event) => {
        event.preventDefault();
        if (demo) {
            window.alert(
                t(
                    "Demonstração em modo leitura. Instale o painel para salvar configurações.",
                ),
            );
            return;
        }
        form.post(panelUrl("/templates/install"), {
            onSuccess: () => setSelected(null),
            preserveScroll: true,
        });
    };
    const change = (resource, payload, action) => {
        if (demo) {
            window.alert(
                t(
                    "Demonstração em modo leitura. Instale o painel para salvar configurações.",
                ),
            );
            return;
        }
        mutation
            .transform(() => ({
                ...payload,
                expected_revision: resource.revision,
            }))
            .post(panelUrl(`/templates/${resource.id}/${action}`), {
                preserveScroll: true,
            });
    };
    return (
        <div className="templates-view">
            <div className="notice">
                <GitBranch size={18} />
                <span>
                    {t(
                        "Versões fixadas, arquivos exportáveis e histórico de cada alteração. Nenhum script é executado durante a instalação.",
                    )}
                </span>
            </div>
            <div className="template-heading">
                <h3>{t("Catálogo de templates")}</h3>
                <a
                    className="text-button"
                    href="https://github.com/qualquer-de-tudo/openpanel-community/blob/main/docs/TEMPLATES.md"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {t("Contribuir com um template")}
                    <ArrowUpRight size={15} />
                </a>
            </div>
            <label className="template-search">
                {t("Buscar templates")}
                <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t("Buscar por nome…")}
                />
            </label>
            <div className="market-grid">
                {grouped
                    .filter((versions) =>
                        `${text(versions[0].name)} ${text(versions[0].description)} ${versions[0].engine}`
                            .toLowerCase()
                            .includes(search.toLowerCase()),
                    )
                    .map((versions) => {
                        const item = versions[0];
                        return (
                            <article className="card market-card" key={item.id}>
                                <span className="app-icon">
                                    <Layers size={24} />
                                </span>
                                <span className="badge">v{item.version}</span>
                                <h3>{text(item.name)}</h3>
                                <p>{text(item.description)}</p>
                                <div className="template-meta">
                                    <span>{item.engine}</span>
                                    <span>
                                        {number(item.file_count)}{" "}
                                        {t("Arquivos")}
                                    </span>
                                    <span>{item.license}</span>
                                </div>
                                <small>
                                    {t("Por :author", { author: item.author })}
                                </small>
                                <button
                                    className="secondary"
                                    onClick={() => choose(versions)}
                                >
                                    <Plus size={15} />
                                    {t("Instalar template")}
                                </button>
                            </article>
                        );
                    })}
            </div>
            <section className="card template-installed">
                <div className="section-head">
                    <div>
                        <h3>{t("Templates instalados")}</h3>
                        <p>
                            {t(
                                "Atualize ou restaure uma versão sem apagar o histórico.",
                            )}
                        </p>
                    </div>
                </div>
                {!installations.length ? (
                    <div className="empty">
                        <Layers size={28} />
                        <p>{t("Nenhum template instalado nesta conta.")}</p>
                    </div>
                ) : (
                    installations.map(({ installation, history, resource }) => {
                        const available = templates
                            .filter(
                                (item) =>
                                    item.id === installation.template_id &&
                                    item.compatible &&
                                    newer(item.version, installation.version) >
                                        0,
                            )
                            .sort((a, b) => newer(b.version, a.version));
                        return (
                            <article
                                className="installed-template"
                                key={installation.id}
                            >
                                <div className="template-heading">
                                    <div>
                                        <h3>{resource.name}</h3>
                                        <p>
                                            {installation.template_id} · v
                                            {installation.version}
                                        </p>
                                    </div>
                                    {demo ? (
                                        <button
                                            className="secondary"
                                            onClick={() =>
                                                window.alert(
                                                    t(
                                                        "Demonstração em modo leitura. Instale o painel para salvar configurações.",
                                                    ),
                                                )
                                            }
                                        >
                                            <Download size={15} />
                                            {t("Exportar projeto")}
                                        </button>
                                    ) : (
                                        <a
                                            className="secondary template-export"
                                            href={panelUrl(`/templates/${resource.id}/download`)}
                                        >
                                            <Download size={15} />
                                            {t("Exportar projeto")}
                                        </a>
                                    )}
                                </div>
                                <code className="template-digest">
                                    SHA-256: {installation.digest}
                                </code>
                                <div className="template-actions">
                                    <label>
                                        {t("Atualizar para")}
                                        <select
                                            value={targets[resource.id] || ""}
                                            onChange={(event) =>
                                                setTargets({
                                                    ...targets,
                                                    [resource.id]:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                {t("Escolha uma versão")}
                                            </option>
                                            {available.map((item) => (
                                                <option
                                                    key={item.version}
                                                    value={item.version}
                                                >
                                                    v{item.version}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <button
                                        className="secondary"
                                        disabled={
                                            !targets[resource.id] ||
                                            mutation.processing
                                        }
                                        onClick={() =>
                                            change(
                                                resource,
                                                {
                                                    version:
                                                        targets[resource.id],
                                                },
                                                "update",
                                            )
                                        }
                                    >
                                        {t("Atualizar template")}
                                    </button>
                                </div>
                                <details>
                                    <summary>
                                        {t("Histórico de versões")}
                                    </summary>
                                    <div className="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>{t("Versão")}</th>
                                                    <th>{t("Ação")}</th>
                                                    <th>{t("Snapshot")}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {history.map((entry) => (
                                                    <tr key={entry.id}>
                                                        <td>
                                                            #{entry.sequence} ·
                                                            v{entry.version}
                                                        </td>
                                                        <td>
                                                            {t(
                                                                {
                                                                    install:
                                                                        "Instalação",
                                                                    update: "Atualização",
                                                                    rollback:
                                                                        "Restauração",
                                                                }[
                                                                    entry.action
                                                                ] ||
                                                                    entry.action,
                                                            )}
                                                        </td>
                                                        <td>
                                                            <code>
                                                                {
                                                                    entry.project_version_id
                                                                }
                                                            </code>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                                <div className="template-actions">
                                    <label>
                                        {t("Restaurar do histórico")}
                                        <select
                                            value={restore[resource.id] || ""}
                                            onChange={(event) =>
                                                setRestore({
                                                    ...restore,
                                                    [resource.id]:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                {t("Escolha uma versão")}
                                            </option>
                                            {history.map((entry) => (
                                                <option
                                                    key={entry.id}
                                                    value={entry.id}
                                                >
                                                    #{entry.sequence} · v
                                                    {entry.version}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <button
                                        className="secondary"
                                        disabled={
                                            !restore[resource.id] ||
                                            mutation.processing
                                        }
                                        onClick={() =>
                                            change(
                                                resource,
                                                {
                                                    history_id:
                                                        restore[resource.id],
                                                },
                                                "rollback",
                                            )
                                        }
                                    >
                                        {t("Restaurar versão")}
                                    </button>
                                </div>
                            </article>
                        );
                    })
                )}
                {Object.values(mutation.errors).map((message, index) => (
                    <p className="error" key={index}>
                        {message}
                    </p>
                ))}
            </section>
            {selected && (
                <div
                    className="modal-backdrop"
                    onClick={() => setSelected(null)}
                >
                    <section
                        className="modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="template-modal-title"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="section-head">
                            <div>
                                <span className="eyebrow">
                                    {t("Instalar template")}
                                </span>
                                <h2 id="template-modal-title">
                                    {text(active?.name)}
                                </h2>
                            </div>
                            <button
                                className="icon"
                                aria-label={t("Fechar")}
                                onClick={() => setSelected(null)}
                            >
                                <X size={22} />
                            </button>
                        </div>
                        <form
                            onSubmit={submit}
                            onInvalidCapture={(event) =>
                                event.target.setCustomValidity(
                                    t("Verifique este campo."),
                                )
                            }
                            onInputCapture={(event) =>
                                event.target.setCustomValidity("")
                            }
                        >
                            <label>
                                {t("Versão")}
                                <select
                                    value={form.data.version}
                                    onChange={(event) =>
                                        form.setData(
                                            "version",
                                            event.target.value,
                                        )
                                    }
                                >
                                    {selected.map((item) => (
                                        <option
                                            key={item.version}
                                            value={item.version}
                                            disabled={!item.compatible}
                                        >
                                            v{item.version}
                                            {!item.compatible
                                                ? " — " +
                                                  t("Painel incompatível")
                                                : ""}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <p>{text(active?.description)}</p>
                            <div className="template-meta">
                                <span>{active?.engine}</span>
                                <span>{number(active?.disk_mb || 0)} MB</span>
                                <span>{active?.license}</span>
                            </div>
                            <details>
                                <summary>{t("Arquivos incluídos")}</summary>
                                <ul className="template-files">
                                    {active?.file_paths.map((path) => (
                                        <li key={path}>
                                            <code>{path}</code>
                                        </li>
                                    ))}
                                </ul>
                            </details>
                            <code className="template-digest">
                                SHA-256: {active?.digest}
                            </code>
                            <label>
                                {t("Nome")}
                                <input
                                    autoFocus
                                    required
                                    pattern="[a-z0-9][a-z0-9._-]*"
                                    maxLength={63}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData("name", event.target.value)
                                    }
                                    placeholder={t("meu-projeto")}
                                />
                            </label>
                            <label>
                                {t("Domínio")} <small>{t("opcional")}</small>
                                <input
                                    value={form.data.domain}
                                    maxLength={253}
                                    pattern="([a-zA-Z0-9][a-zA-Z0-9-]*\.)+[a-zA-Z]{2,63}"
                                    onChange={(event) =>
                                        form.setData(
                                            "domain",
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t("app.seudominio.com")}
                                />
                            </label>
                            {Object.values(form.errors).map(
                                (message, index) => (
                                    <p className="error" key={index}>
                                        {message}
                                    </p>
                                ),
                            )}
                            <div className="notice">
                                {t(
                                    "A instalação cria arquivos de projeto. Publicar a aplicação no Linux ainda depende do executor.",
                                )}
                            </div>
                            <div className="modal-actions">
                                <button
                                    type="button"
                                    className="secondary"
                                    onClick={() => setSelected(null)}
                                >
                                    {t("Cancelar")}
                                </button>
                                <button
                                    className="primary"
                                    disabled={
                                        form.processing || !active?.compatible
                                    }
                                >
                                    {form.processing
                                        ? t("Salvando…")
                                        : t("Instalar template")}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            )}
        </div>
    );
}
