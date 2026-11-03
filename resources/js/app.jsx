import { panelUrl } from "./panel-url.mjs";
import React, { useState } from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp, router, useForm } from "@inertiajs/react";
import {
    LayoutDashboard,
    Globe,
    Layers,
    Database,
    Mail,
    ShieldCheck,
    LockKeyhole,
    ArrowLeftRight,
    Clock3,
    Plug,
    Settings,
    Search,
    Plus,
    ArrowUpRight,
    ChevronDown,
    ChevronRight,
    Box,
    Terminal,
    X,
    Trash2,
    LogOut,
    Activity,
    Cpu,
    HardDrive,
    MemoryStick,
    Check,
    Server,
    ExternalLink,
    Sparkles,
    BookOpen,
    Menu,
    Download,
    GitBranch,
} from "lucide-react";
import "../css/app.css";
import { I18nProvider, LanguageSelector, useI18n } from "./i18n.jsx";
import release from "../data/release.json";
import Templates from "./Templates.jsx";
import Maintenance from "./Maintenance.jsx";
const nav = [
    ["overview", "Visão geral", LayoutDashboard],
    ["sites", "Sites e projetos", Globe],
    ["services", "Serviços e runtimes", Layers],
    ["databases", "Bancos de dados", Database],
    ["docker", "Docker", Box],
    ["email", "E-mail", Mail],
    ["certificates", "Certificados SSL", LockKeyhole],
    ["proxy", "Proxy reverso", ArrowLeftRight],
    ["security", "Segurança", ShieldCheck],
    ["crons", "Tarefas agendadas", Clock3],
    ["integrations", "Integrações", Plug],
    ["marketplace", "Marketplace", Download],
    ["templates", "Templates", Layers],
    ["accounts", "Contas e limites", Settings],
    ["maintenance", "Manutenção do servidor", ShieldCheck],
];
const descriptions = {
    maintenance: "Versões oficiais e verificações de segurança do servidor.",
    sites: "Seus projetos, do repositório ao servidor.",
    services: "Escolha a stack ideal para cada aplicação.",
    databases: "Dados organizados, ambientes independentes.",
    docker: "Gerencie seus projetos com Docker Compose.",
    email: "Planeje seu próprio servidor de e-mail.",
    certificates: "HTTPS com Let’s Encrypt e Cloudflare DNS.",
    proxy: "Direcione o tráfego para suas aplicações.",
    security: "Proteção em todas as camadas do servidor.",
    crons: "Organize as rotinas das suas aplicações.",
    integrations: "Conecte repositórios e assistentes de deploy.",
    marketplace: "Uma nova possibilidade para o seu servidor.",
};
function Login({ setup, defaults = {} }) {
    const { t } = useI18n();
    const f = useForm({
        name: defaults.name || "",
        email: defaults.email || "",
        password: "",
        password_confirmation: "",
    });
    return (
        <div className="auth">
            <div className="auth-art">
                <Brand />
                <span className="eyebrow">
                    {" "}
                    {t("SEU SERVIDOR. SUAS POSSIBILIDADES.")}{" "}
                </span>
                <h1>
                    {" "}
                    {t("Uma casa para")} <br /> {t("todas as suas")} <br />
                    <em>{t("ideias.")}</em>
                </h1>
                <p>
                    {" "}
                    {t(
                        "Do primeiro deploy ao próximo grande projeto.",
                    )} <br /> {t("Tudo sob seu controle, em um só lugar.")}{" "}
                </p>
                <div className="auth-grid">
                    {[Globe, Database, Box, ShieldCheck].map((I, i) => (
                        <div key={i}>
                            <I size={30} />
                        </div>
                    ))}
                </div>
                <small>
                    {t("OpenPanel · Infraestrutura com simplicidade")}
                </small>
            </div>
            <div className="auth-form">
                <LanguageSelector />
                <span className="badge">
                    {setup ? t("PRIMEIRO ACESSO") : t("BEM-VINDO DE VOLTA")}
                </span>
                <h2>
                    {setup
                        ? t("Seu painel começa aqui.")
                        : t("Bom ter você por aqui.")}
                </h2>
                <p>
                    {setup
                        ? t(
                              "Crie a conta administradora para começar a organizar seu servidor.",
                          )
                        : t("Entre para cuidar dos seus projetos.")}
                </p>
                <form
                    onInvalidCapture={(event) =>
                        event.target.setCustomValidity(
                            t("Verifique este campo."),
                        )
                    }
                    onInputCapture={(event) =>
                        event.target.setCustomValidity("")
                    }
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(panelUrl(setup ? "/setup" : "/login"));
                    }}
                >
                    {(setup
                        ? ["name", "email", "password", "password_confirmation"]
                        : ["email", "password"]
                    ).map((k) => (
                        <label key={k}>
                            {
                                {
                                    name: t("Seu nome"),
                                    email: t("E-mail"),
                                    password: t("Senha"),
                                    password_confirmation:
                                        t("Confirme a senha"),
                                }[k]
                            }
                            <input
                                required
                                minLength={
                                    k.includes("password") && setup
                                        ? 12
                                        : undefined
                                }
                                type={
                                    k.includes("password")
                                        ? "password"
                                        : k === "email"
                                          ? "email"
                                          : "text"
                                }
                                value={f.data[k]}
                                onChange={(e) => f.setData(k, e.target.value)}
                                autoComplete={
                                    k.includes("password")
                                        ? setup
                                            ? "new-password"
                                            : "current-password"
                                        : k === "email"
                                          ? "email"
                                          : "name"
                                }
                            />
                            <small className="error">{f.errors[k]}</small>
                        </label>
                    ))}
                    <button className="primary" disabled={f.processing}>
                        {setup ? t("Criar meu painel") : t("Entrar no painel")}
                        <ArrowUpRight size={17} />
                    </button>
                </form>
                <small>
                    {" "}
                    {t(
                        "Conexão local por IP ou domínio configurado no servidor.",
                    )}{" "}
                </small>
            </div>
        </div>
    );
}
function Brand() {
    return (
        <div className="brand">
            <span>
                <Layers size={22} />
            </span>
            open<span className="brand-light">panel</span>
            <sup>community</sup>
        </div>
    );
}
export function Panel({
    resources,
    account,
    catalog,
    user,
    server,
    message,
    messageKey,
    demo = false,
    templates = [],
    templateInstallations = [],
    maintenance = null,
}) {
    const { t, number } = useI18n();
    const [page, setPage] = useState("overview"),
        [search, setSearch] = useState(""),
        [modal, setModal] = useState(null),
        [mobile, setMobile] = useState(false),
        [filter, setFilter] = useState("all");
    const f = useForm({
        kind: "sites",
        name: "",
        engine: "Laravel",
        domain: "",
        repository: "",
        disk_mb: 512,
    });
    const removeResource = (url) => {
        if (demo) {
            window.alert(
                t(
                    "Demonstração: alterações não são enviadas a nenhum servidor.",
                ),
            );
            return;
        }
        router.delete(panelUrl(url));
    };
    const used = resources.reduce((s, r) => s + r.disk_mb, 0);
    const projects = resources.filter((r) => r.kind === "sites");
    const open = (kind = "sites", engine = null) => {
        f.reset();
        f.clearErrors();
        f.setData({
            kind,
            name: "",
            engine: engine || catalog[kind][0],
            domain: "",
            repository: "",
            disk_mb: 512,
        });
        setModal(kind);
    };
    const go = (p) => {
        setPage(p);
        setSearch("");
        setMobile(false);
    };
    const kind = page === "docker" ? "sites" : page;
    const visible = resources.filter(
        (r) =>
            (page === "docker"
                ? r.engine === "Docker Compose"
                : r.kind === kind) &&
            `${r.name} ${t(r.engine)} ${r.domain || ""}`
                .toLowerCase()
                .includes(search.toLowerCase()),
    );
    return (
        <div className="shell">
            <aside className={mobile ? "sidebar mobile" : "sidebar"}>
                <Brand />
                <div className="workspace">
                    <span className="workspace-icon">O</span>
                    <div>
                        {" "}
                        {t("Meu workspace")}
                        <small>{t("Workspace isolado")}</small>
                    </div>
                    <ChevronDown size={15} />
                </div>
                <span className="nav-caption">{t("WORKSPACE")}</span>
                <nav>
                    {nav.filter(([id]) => id !== "maintenance" || demo || user.is_operator).map(([id, label, I], i) => (
                        <React.Fragment key={id}>
                            {i === 5 && (
                                <span className="nav-caption spaced">
                                    {" "}
                                    {t("INFRAESTRUTURA")}{" "}
                                </span>
                            )}
                            {i === 10 && (
                                <span className="nav-caption spaced">
                                    {" "}
                                    {t("GERENCIAMENTO")}{" "}
                                </span>
                            )}
                            <button
                                onClick={() => go(id)}
                                className={page === id ? "active" : ""}
                            >
                                <I size={18} />
                                {t(label)}
                                {id === "sites" && (
                                    <span className="count">
                                        {projects.length}
                                    </span>
                                )}
                                {id === "marketplace" && (
                                    <span className="new">{t("novo")}</span>
                                )}
                            </button>
                        </React.Fragment>
                    ))}
                </nav>
                <div className="sidebar-foot">
                    <div className="help">
                        <BookOpen size={18} />
                        <div>
                            {" "}
                            {t("Construído para você")}{" "}
                            <small>
                                {t("Edição comunitária · :version", {
                                    version: release.version,
                                })}
                            </small>
                        </div>
                    </div>
                    <button
                        className="profile"
                        onClick={() =>
                            demo
                                ? window.alert(
                                      t(
                                          "Você está navegando em uma demonstração pública, sem sessão administrativa.",
                                      ),
                                  )
                                : router.post(panelUrl("/logout"))
                        }
                        title={t("Sair")}
                    >
                        <span className="avatar">
                            {user.name.slice(0, 2).toUpperCase()}
                        </span>
                        <div>
                            {demo ? t("Visitante") : user.name}
                            <small>{t("Titular da conta")}</small>
                        </div>
                        <LogOut size={16} />
                    </button>
                </div>
            </aside>
            <div className="main">
                <header>
                    <button
                        className="icon mobile-toggle"
                        aria-label={t("Abrir navegação")}
                        onClick={() => setMobile(!mobile)}
                    >
                        <Menu size={20} />
                    </button>
                    <div className="breadcrumb">
                        {" "}
                        {t("Workspace")} <ChevronRight size={13} />{" "}
                        <strong>
                            {t(nav.find((n) => n[0] === page)?.[1])}
                        </strong>
                    </div>
                    <div className="header-right">
                        <LanguageSelector />
                        {demo && (
                            <a
                                className="primary install-button"
                                href="https://github.com/qualquer-de-tudo/openpanel-community"
                                target="_blank"
                                rel="noopener noreferrer"
                                title={t("Instalar o OpenPanel pelo GitHub")}
                            >
                                <Download size={16} />
                                {t("Instalar")}
                            </a>
                        )}
                        <span className="connection">
                            <i /> {t("Painel conectado")}{" "}
                        </span>
                        <span className="separator" />
                        <span className="avatar tiny">
                            {user.name.slice(0, 2).toUpperCase()}
                        </span>
                    </div>
                </header>
                <main>
                    <div className="page-heading">
                        <div>
                            <span className="eyebrow">
                                {" "}
                                {t("SEU SERVIDOR, SIMPLIFICADO")}{" "}
                            </span>
                            <h1>
                                {page === "overview"
                                    ? t("Visão geral")
                                    : t(nav.find((n) => n[0] === page)?.[1])}
                                <span className="heading-dot">.</span>
                            </h1>
                            <p>
                                {page === "overview"
                                    ? t(
                                          "Olá, :name. Vamos dar vida ao seu próximo projeto?",
                                          {
                                              name: demo
                                                  ? t("Visitante")
                                                  : user.name.split(" ")[0],
                                          },
                                      )
                                    : page === "templates"
                                      ? t(
                                            "Projetos prontos para começar, com versões sob seu controle.",
                                        )
                                      : t(descriptions[page]) ||
                                        t(
                                            "Recursos organizados para cada conta.",
                                        )}
                            </p>
                        </div>
                        <button
                            style={
                                (page === "templates" || page === "maintenance")
                                    ? { display: "none" }
                                    : undefined
                            }
                            className="primary"
                            onClick={() =>
                                open(
                                    page === "docker"
                                        ? "sites"
                                        : catalog[page]
                                          ? page
                                          : "sites",
                                    page === "docker" ? "Docker Compose" : null,
                                )
                            }
                        >
                            <Plus size={17} />
                            {page === "overview"
                                ? t("Novo projeto")
                                : t("Nova configuração")}
                        </button>
                    </div>
                    {demo && (
                        <div className="notice">
                            <BookOpen size={18} />
                            <span>
                                <strong>{t("Demonstração pública")}</strong>{" "}
                                {t(
                                    "· Dados fictícios e navegação em modo leitura. Nenhuma operação é executada em servidores.",
                                )}{" "}
                            </span>
                        </div>
                    )}
                    {message && (
                        <div className="notice success">
                            <Check size={17} />
                            {t(messageKey || message)}
                        </div>
                    )}
                    {page === "overview" ? (
                        <>
                            <div className="hero">
                                <div className="hero-content">
                                    <span className="hero-label">
                                        <span />{" "}
                                        {t(
                                            "SEU PRÓXIMO DEPLOY COMEÇA AQUI",
                                        )}{" "}
                                    </span>
                                    <h2>
                                        {" "}
                                        {t("Menos complexidade.")} <br />{" "}
                                        {t("Mais possibilidades.")}{" "}
                                    </h2>
                                    <p>
                                        {" "}
                                        {t(
                                            "Sites, containers e bancos de dados.",
                                        )}{" "}
                                        <br />{" "}
                                        {t(
                                            "Sua infraestrutura inteira, no mesmo lugar.",
                                        )}{" "}
                                    </p>
                                    <button onClick={() => open()}>
                                        {" "}
                                        {t("Criar meu primeiro projeto")}{" "}
                                        <ArrowUpRight size={17} />
                                    </button>
                                </div>
                                <div className="server-illustration">
                                    <div className="orbit orbit-one" />
                                    <div className="orbit orbit-two" />
                                    <div className="float-tag tag-top">
                                        <Globe size={16} />{" "}
                                        {t("seus projetos.online")}{" "}
                                    </div>
                                    <div className="server-stack">
                                        {[1, 2, 3].map((i) => (
                                            <div
                                                className="server-slab"
                                                key={i}
                                            >
                                                <span className="server-lines" />
                                                <span className="server-lines" />
                                                <i />
                                                <i />
                                            </div>
                                        ))}
                                    </div>
                                    <div className="float-tag tag-bottom">
                                        <ShieldCheck size={16} />{" "}
                                        {t("Seu espaço para construir")}{" "}
                                    </div>
                                    <span className="spark s1">+</span>
                                    <span className="spark s2">+</span>
                                </div>
                            </div>
                            <div className="metrics">
                                {[
                                    [
                                        Globe,
                                        t("Projetos"),
                                        projects.length,
                                        t("de :count disponíveis", {
                                            count: number(
                                                account?.project_limit || 0,
                                            ),
                                        }),
                                    ],
                                    [
                                        Database,
                                        t("Bancos de dados"),
                                        resources.filter(
                                            (r) => r.kind === "databases",
                                        ).length,
                                        t("de :count disponíveis", {
                                            count: number(
                                                account?.database_limit || 0,
                                            ),
                                        }),
                                    ],
                                    [
                                        HardDrive,
                                        t("Espaço reservado"),
                                        `${number(used / 1024, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} GB`,
                                        t(":amount GB no seu plano", {
                                            amount: number(
                                                (account?.disk_mb || 0) / 1024,
                                            ),
                                        }),
                                    ],
                                    [
                                        MemoryStick,
                                        t("Memória do plano"),
                                        `${number((account?.memory_mb || 0) / 1024)} GB`,
                                        t("Limite planejado por conta"),
                                    ],
                                ].map(([I, label, value, sub], i) => (
                                    <div className="metric" key={t(label)}>
                                        <div className="metric-label">
                                            {t(label)}
                                            <I size={17} />
                                        </div>
                                        <strong>{value}</strong>
                                        <div className="metric-bottom">
                                            <span>{sub}</span>
                                            {i < 2 ? (
                                                <span className="mini-tag">
                                                    {" "}
                                                    {t("cota")}{" "}
                                                </span>
                                            ) : (
                                                <div className="mini-track">
                                                    <i
                                                        style={{
                                                            width: `${i === 2 ? Math.min(100, (used / (account?.disk_mb || 1)) * 100) : 0}%`,
                                                        }}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="content-grid">
                                <section className="card projects">
                                    <div className="section-head">
                                        <div>
                                            <h3>
                                                {" "}
                                                {t("Seus projetos")}{" "}
                                                <span className="number">
                                                    {projects.length}
                                                </span>
                                            </h3>
                                            <p>
                                                {t("Um lugar para cada ideia.")}
                                            </p>
                                        </div>
                                        <button
                                            className="text-button"
                                            onClick={() => go("sites")}
                                        >
                                            {" "}
                                            {t("Ver todos")}{" "}
                                            <ArrowUpRight size={15} />
                                        </button>
                                    </div>
                                    {projects.length ? (
                                        <ResourceTable
                                            rows={projects.slice(0, 4)}
                                            remove={(id) =>
                                                removeResource(
                                                    `/resources/${id}`,
                                                )
                                            }
                                        />
                                    ) : (
                                        <div className="empty">
                                            <div className="empty-icon">
                                                <Globe size={26} />
                                            </div>
                                            <h3>
                                                {" "}
                                                {t(
                                                    "Grandes ideias começam com um deploy.",
                                                )}{" "}
                                            </h3>
                                            <p>
                                                {" "}
                                                {t(
                                                    "Conecte seu repositório ou escolha uma stack",
                                                )}{" "}
                                                <br />{" "}
                                                {t(
                                                    "para preparar seu primeiro projeto.",
                                                )}{" "}
                                            </p>
                                            <button
                                                className="secondary"
                                                onClick={() => open()}
                                            >
                                                <Plus size={16} />{" "}
                                                {t("Adicionar projeto")}{" "}
                                            </button>
                                        </div>
                                    )}
                                    <div className="card-footer">
                                        <GitBranch size={15} />{" "}
                                        {t(
                                            "Seu código, seu repositório, suas regras.",
                                        )}{" "}
                                        <button
                                            onClick={() => go("integrations")}
                                        >
                                            {" "}
                                            {t("Conectar Git")}{" "}
                                            <ArrowUpRight size={14} />
                                        </button>
                                    </div>
                                </section>
                                <section className="card server-info">
                                    <div className="section-head">
                                        <div>
                                            <h3>{t("Seu ambiente")}</h3>
                                            <p>
                                                {t(
                                                    "Informações desta instalação",
                                                )}
                                            </p>
                                        </div>
                                        <span className="server-icon">
                                            <Server size={20} />
                                        </span>
                                    </div>
                                    <div className="hostname">
                                        <span className="server-avatar">
                                            <Terminal size={24} />
                                        </span>
                                        <div>
                                            <strong>{server.hostname}</strong>
                                            <small>
                                                {t(server.platform)} · PHP{" "}
                                                {server.php}
                                            </small>
                                        </div>
                                    </div>
                                    <div className="detail-row">
                                        <span>{t("Agente Linux")}</span>
                                        <span className="status warning">
                                            {" "}
                                            {t("Não conectado")}{" "}
                                        </span>
                                    </div>
                                    <div className="detail-row">
                                        <span>{t("Monitoramento")}</span>
                                        <strong>
                                            {t("Aguardando agente")}
                                        </strong>
                                    </div>
                                    <div className="detail-row">
                                        <span>{t("Provisionamento")}</span>
                                        <strong>{t("Planejamento")}</strong>
                                    </div>
                                    <div className="agent-note">
                                        <Activity size={17} />
                                        <span>
                                            {" "}
                                            {t(
                                                "Métricas reais de CPU, RAM e tráfego estarão disponíveis ao conectar o agente.",
                                            )}{" "}
                                        </span>
                                    </div>
                                </section>
                            </div>
                            <section className="quick-section">
                                <div className="section-head">
                                    <div>
                                        <h3>{t("Sua stack, do seu jeito")}</h3>
                                        <p>
                                            {" "}
                                            {t(
                                                "As ferramentas que você já conhece. Juntas.",
                                            )}{" "}
                                        </p>
                                    </div>
                                    <button
                                        className="text-button"
                                        onClick={() => go("marketplace")}
                                    >
                                        {" "}
                                        {t("Explorar marketplace")}{" "}
                                        <ArrowUpRight size={15} />
                                    </button>
                                </div>
                                <div className="quick-grid">
                                    {[
                                        [
                                            "N",
                                            "Nginx",
                                            t("Servidor web"),
                                            "services",
                                        ],
                                        [
                                            "P",
                                            "PostgreSQL",
                                            t("Banco de dados"),
                                            "databases",
                                        ],
                                        [
                                            "D",
                                            "Docker Compose",
                                            t("Containers"),
                                            "sites",
                                        ],
                                        [
                                            "L",
                                            "Laravel",
                                            t("Framework PHP"),
                                            "sites",
                                        ],
                                    ].map(([letter, name, sub, k], i) => (
                                        <button
                                            className={`quick-card color-${i}`}
                                            key={name}
                                            onClick={() => open(k, name)}
                                        >
                                            <span className="app-icon">
                                                {letter}
                                            </span>
                                            <div>
                                                <strong>{name}</strong>
                                                <small>{sub}</small>
                                            </div>
                                            <Plus size={18} />
                                        </button>
                                    ))}
                                </div>
                            </section>
                        </>
                    ) : page === "templates" ? (
                        <Templates
                            templates={templates}
                            installations={templateInstallations}
                            demo={demo}
                        />
                    ) : page === "maintenance" ? (
                        <Maintenance data={maintenance} demo={demo} />
                    ) : page === "accounts" ? (
                        <section className="card account-card">
                            <h3>{t("Meu plano de hospedagem")}</h3>
                            <p>
                                {" "}
                                {t(
                                    "Dados isolados por conta. As cotas abaixo validam a reserva de recursos.",
                                )}{" "}
                            </p>
                            <div className="plan-grid">
                                {[
                                    [t("Projetos"), account?.project_limit],
                                    [
                                        t("Bancos de dados"),
                                        account?.database_limit,
                                    ],
                                    [
                                        t("Disco"),
                                        `${account?.disk_mb / 1024} GB`,
                                    ],
                                    [
                                        t("Memória"),
                                        `${account?.memory_mb / 1024} GB`,
                                    ],
                                ].map(([k, v]) => (
                                    <div key={k}>
                                        <small>{t(k)}</small>
                                        <strong>{v}</strong>
                                    </div>
                                ))}
                            </div>
                            <div className="notice">
                                {" "}
                                {t(
                                    "O isolamento de CPU, memória e disco no sistema operacional requer integração com o agente Linux.",
                                )}{" "}
                            </div>
                        </section>
                    ) : page === "marketplace" ? (
                        <>
                            <div className="toolbar">
                                <div className="search">
                                    <Search size={17} />
                                    <input
                                        placeholder={t("Buscar aplicativos…")}
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                </div>
                                <select
                                    value={filter}
                                    onChange={(e) => setFilter(e.target.value)}
                                >
                                    <option value="all">{t("Todos")}</option>
                                    {Object.keys(catalog).map((k) => (
                                        <option key={k} value={k}>
                                            {t(
                                                nav.find(
                                                    (n) => n[0] === k,
                                                )?.[1],
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="market-grid">
                                {Object.entries(catalog)
                                    .filter(
                                        ([k]) =>
                                            filter === "all" || filter === k,
                                    )
                                    .flatMap(([k, apps]) =>
                                        apps
                                            .filter((a) =>
                                                t(a)
                                                    .toLowerCase()
                                                    .includes(
                                                        search.toLowerCase(),
                                                    ),
                                            )
                                            .map((a, i) => (
                                                <article
                                                    className={`card market-card color-${i % 4}`}
                                                    key={a}
                                                >
                                                    <span className="app-icon">
                                                        {a.slice(0, 1)}
                                                    </span>
                                                    <span className="badge">
                                                        {" "}
                                                        {t(
                                                            "Receita planejada",
                                                        )}{" "}
                                                    </span>
                                                    <h3>{t(a)}</h3>
                                                    <p>
                                                        {t(
                                                            nav.find(
                                                                (n) =>
                                                                    n[0] === k,
                                                            )?.[1],
                                                        )}{" "}
                                                        {t(
                                                            "· Configuração por workspace",
                                                        )}{" "}
                                                    </p>
                                                    <button
                                                        className="secondary"
                                                        onClick={() =>
                                                            open(k, a)
                                                        }
                                                    >
                                                        {" "}
                                                        {t("Configurar")}{" "}
                                                        <Plus size={15} />
                                                    </button>
                                                </article>
                                            )),
                                    )}
                            </div>
                            <div className="notice">
                                {" "}
                                {t(
                                    "O catálogo apresenta opções de configuração. Compatibilidade e instalação serão verificadas pelo executor Linux.",
                                )}{" "}
                            </div>
                        </>
                    ) : (
                        <section className="card">
                            <div className="section-head">
                                <div>
                                    <h3>
                                        {t(nav.find((n) => n[0] === page)?.[1])}{" "}
                                        <span className="number">
                                            {visible.length}
                                        </span>
                                    </h3>
                                    <p>{t("Configurações do seu workspace")}</p>
                                </div>
                                <div className="search">
                                    <Search size={16} />
                                    <input
                                        placeholder={t("Buscar por nome…")}
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            {visible.length ? (
                                <ResourceTable
                                    rows={visible}
                                    remove={(id) => {
                                        if (
                                            window.confirm(
                                                t(
                                                    "Remover esta configuração do painel?",
                                                ),
                                            )
                                        )
                                            removeResource(`/resources/${id}`);
                                    }}
                                />
                            ) : (
                                <div className="empty">
                                    <div className="empty-icon">
                                        <Box size={28} />
                                    </div>
                                    <h3>
                                        {search
                                            ? t("Nenhum resultado encontrado")
                                            : t(
                                                  "Seu espaço está pronto para começar.",
                                              )}
                                    </h3>
                                    <p>
                                        {search
                                            ? t(
                                                  "Experimente buscar outro nome.",
                                              )
                                            : t(
                                                  "Adicione uma configuração para organizar seu servidor.",
                                              )}
                                    </p>
                                    <button
                                        className="secondary"
                                        onClick={() =>
                                            open(
                                                kind,
                                                page === "docker"
                                                    ? "Docker Compose"
                                                    : null,
                                            )
                                        }
                                    >
                                        <Plus size={16} />{" "}
                                        {t("Adicionar configuração")}{" "}
                                    </button>
                                </div>
                            )}
                            <div className="card-footer">
                                <Activity size={15} />{" "}
                                {t(
                                    "Configurações planejadas · execução depende do agente Linux.",
                                )}{" "}
                            </div>
                        </section>
                    )}
                    <footer>
                        <span>
                            <span className="footer-dot" /> OpenPanel Community
                        </span>
                        <span>
                            {t("Feito para construir. Livre para crescer.")}
                        </span>
                        <span>
                            Laravel + React + Inertia{" "}
                            <span className="version">{release.version}</span>
                        </span>
                    </footer>
                </main>
            </div>
            {modal && (
                <div className="modal-backdrop" onClick={() => setModal(null)}>
                    <section
                        className="modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="modal-title"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="section-head">
                            <div>
                                <span className="eyebrow">
                                    {" "}
                                    {t("ADICIONAR AO WORKSPACE")}{" "}
                                </span>
                                <h2 id="modal-title">
                                    {t(nav.find((n) => n[0] === modal)?.[1])}
                                </h2>
                            </div>
                            <button
                                className="icon"
                                onClick={() => setModal(null)}
                                aria-label={t("Fechar")}
                            >
                                <X size={22} />
                            </button>
                        </div>
                        <form
                            onInvalidCapture={(event) =>
                                event.target.setCustomValidity(
                                    t("Verifique este campo."),
                                )
                            }
                            onInputCapture={(event) =>
                                event.target.setCustomValidity("")
                            }
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (demo) {
                                    window.alert(
                                        t(
                                            "Demonstração em modo leitura. Instale o painel para salvar configurações.",
                                        ),
                                    );
                                    return;
                                }
                                f.post(panelUrl("/resources"), {
                                    onSuccess: () => setModal(null),
                                });
                            }}
                        >
                            <label>
                                {" "}
                                {t("Nome")}{" "}
                                <input
                                    autoFocus
                                    required
                                    pattern="[a-z0-9][a-z0-9._-]*"
                                    maxLength={63}
                                    placeholder={t("meu-projeto")}
                                    value={f.data.name}
                                    onChange={(e) =>
                                        f.setData("name", e.target.value)
                                    }
                                />
                                <small className="error">{f.errors.name}</small>
                            </label>
                            <div className="form-row">
                                <label>
                                    {" "}
                                    {t("Stack / aplicativo")}{" "}
                                    <select
                                        value={f.data.engine}
                                        onChange={(e) =>
                                            f.setData("engine", e.target.value)
                                        }
                                    >
                                        {catalog[modal].map((a) => (
                                            <option key={a} value={a}>
                                                {t(a)}
                                            </option>
                                        ))}
                                    </select>
                                    <small className="error">
                                        {f.errors.engine}
                                    </small>
                                </label>
                                <label>
                                    {" "}
                                    {t("Reserva de disco (MB)")}{" "}
                                    <input
                                        type="number"
                                        required
                                        min={64}
                                        max={account?.disk_mb}
                                        value={f.data.disk_mb}
                                        onChange={(e) =>
                                            f.setData("disk_mb", e.target.value)
                                        }
                                    />
                                    <small className="error">
                                        {f.errors.disk_mb}
                                    </small>
                                </label>
                            </div>
                            <label>
                                {" "}
                                {t("Domínio")} <small>{t("opcional")}</small>
                                <input
                                    placeholder={t("app.seudominio.com")}
                                    maxLength={253}
                                    pattern="([a-zA-Z0-9][a-zA-Z0-9-]*\.)+[a-zA-Z]{2,63}"
                                    value={f.data.domain}
                                    onChange={(e) =>
                                        f.setData("domain", e.target.value)
                                    }
                                />
                                <small className="error">
                                    {f.errors.domain}
                                </small>
                            </label>
                            <label>
                                {" "}
                                {t("Repositório HTTPS")}{" "}
                                <small>{t("opcional")}</small>
                                <input
                                    type="url"
                                    pattern="https://.*"
                                    placeholder={t(
                                        "https://github.com/voce/projeto",
                                    )}
                                    value={f.data.repository}
                                    onChange={(e) =>
                                        f.setData("repository", e.target.value)
                                    }
                                />
                                <small className="error">
                                    {f.errors.repository}
                                </small>
                            </label>
                            <div className="notice">
                                <Terminal size={18} />
                                <span>
                                    {" "}
                                    {t(
                                        "Esta ação salva o planejamento. Nenhum serviço será instalado até a integração com o agente Linux.",
                                    )}{" "}
                                </span>
                            </div>
                            <div className="modal-actions">
                                <button
                                    type="button"
                                    className="secondary"
                                    onClick={() => setModal(null)}
                                >
                                    {" "}
                                    {t("Cancelar")}{" "}
                                </button>
                                <button
                                    className="primary"
                                    disabled={f.processing}
                                >
                                    <Plus size={16} />
                                    {f.processing
                                        ? t("Salvando…")
                                        : t("Salvar configuração")}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            )}
        </div>
    );
}
function ResourceTable({ rows, remove }) {
    const { t, number } = useI18n();
    return (
        <div className="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{t("PROJETO / RECURSO")}</th>
                        <th>{t("STACK")}</th>
                        <th>{t("STATUS")}</th>
                        <th>{t("DISCO")}</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.id}>
                            <td>
                                <strong>{r.name}</strong>
                                <small>
                                    {r.domain || t("Sem domínio configurado")}
                                </small>
                            </td>
                            <td>
                                <span className="stack-label">
                                    {t(r.engine)}
                                </span>
                            </td>
                            <td>
                                <span className="status warning">
                                    {" "}
                                    {t("Planejado")}{" "}
                                </span>
                            </td>
                            <td>{number(r.disk_mb)} MB</td>
                            <td>
                                <button
                                    className="icon delete"
                                    aria-label={t("Remover :name", {
                                        name: r.name,
                                    })}
                                    onClick={() => remove(r.id)}
                                >
                                    <Trash2 size={16} />
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
if (
    document.getElementById("app")?.hasAttribute("data-page") ||
    document.querySelector('script[data-page="app"][type="application/json"]')
)
    createInertiaApp({
        resolve: (name) => ({ Panel, Login })[name],
        setup({ el, App, props }) {
            createRoot(el).render(
                <I18nProvider initialLocale={props.initialPage.props.locale}>
                    <App {...props} />
                </I18nProvider>,
            );
        },
        progress: { color: "#25896d" },
    });
