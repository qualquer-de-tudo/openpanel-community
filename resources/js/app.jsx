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
    ["accounts", "Contas e limites", Settings],
];
const descriptions = {
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
function Login({ setup }) {
    const f = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });
    return (
        <div className="auth">
            <div className="auth-art">
                <Brand />
                <span className="eyebrow">
                    SEU SERVIDOR. SUAS POSSIBILIDADES.
                </span>
                <h1>
                    Uma casa para
                    <br />
                    todas as suas
                    <br />
                    <em>ideias.</em>
                </h1>
                <p>
                    Do primeiro deploy ao próximo grande projeto.
                    <br />
                    Tudo sob seu controle, em um só lugar.
                </p>
                <div className="auth-grid">
                    {[Globe, Database, Box, ShieldCheck].map((I, i) => (
                        <div key={i}>
                            <I size={30} />
                        </div>
                    ))}
                </div>
                <small>OpenPanel · Infraestrutura com simplicidade</small>
            </div>
            <div className="auth-form">
                <span className="badge">
                    {setup ? "PRIMEIRO ACESSO" : "BEM-VINDO DE VOLTA"}
                </span>
                <h2>
                    {setup
                        ? "Seu painel começa aqui."
                        : "Bom ter você por aqui."}
                </h2>
                <p>
                    {setup
                        ? "Crie a conta administradora para começar a organizar seu servidor."
                        : "Entre para cuidar dos seus projetos."}
                </p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(setup ? "/setup" : "/login");
                    }}
                >
                    {(setup
                        ? ["name", "email", "password", "password_confirmation"]
                        : ["email", "password"]
                    ).map((k) => (
                        <label key={k}>
                            {
                                {
                                    name: "Seu nome",
                                    email: "E-mail",
                                    password: "Senha",
                                    password_confirmation: "Confirme a senha",
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
                        {setup ? "Criar meu painel" : "Entrar no painel"}
                        <ArrowUpRight size={17} />
                    </button>
                </form>
                <small>
                    Conexão local por IP ou domínio configurado no servidor.
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
    demo = false,
}) {
    const [page, setPage] = useState("overview"),
        [search, setSearch] = useState(""),
        [modal, setModal] = useState(null),
        [mobile, setMobile] = useState(false),
        [filter, setFilter] = useState("Todos");
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
                "Demonstração: alterações não são enviadas a nenhum servidor.",
            );
            return;
        }
        router.delete(url);
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
            `${r.name} ${r.engine} ${r.domain || ""}`
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
                        Meu workspace<small>Workspace isolado</small>
                    </div>
                    <ChevronDown size={15} />
                </div>
                <span className="nav-caption">WORKSPACE</span>
                <nav>
                    {nav.map(([id, label, I], i) => (
                        <React.Fragment key={id}>
                            {i === 5 && (
                                <span className="nav-caption spaced">
                                    INFRAESTRUTURA
                                </span>
                            )}
                            {i === 10 && (
                                <span className="nav-caption spaced">
                                    GERENCIAMENTO
                                </span>
                            )}
                            <button
                                onClick={() => go(id)}
                                className={page === id ? "active" : ""}
                            >
                                <I size={18} />
                                {label}
                                {id === "sites" && (
                                    <span className="count">
                                        {projects.length}
                                    </span>
                                )}
                                {id === "marketplace" && (
                                    <span className="new">novo</span>
                                )}
                            </button>
                        </React.Fragment>
                    ))}
                </nav>
                <div className="sidebar-foot">
                    <div className="help">
                        <BookOpen size={18} />
                        <div>
                            Construído para você
                            <small>Community edition · v0.1</small>
                        </div>
                    </div>
                    <button
                        className="profile"
                        onClick={() =>
                            demo
                                ? window.alert(
                                      "Você está navegando em uma demonstração pública, sem sessão administrativa.",
                                  )
                                : router.post("/logout")
                        }
                        title="Sair"
                    >
                        <span className="avatar">
                            {user.name.slice(0, 2).toUpperCase()}
                        </span>
                        <div>
                            {user.name}
                            <small>Titular da conta</small>
                        </div>
                        <LogOut size={16} />
                    </button>
                </div>
            </aside>
            <div className="main">
                <header>
                    <button
                        className="icon mobile-toggle"
                        onClick={() => setMobile(!mobile)}
                    >
                        <Menu size={20} />
                    </button>
                    <div className="breadcrumb">
                        Workspace <ChevronRight size={13} />{" "}
                        <strong>{nav.find((n) => n[0] === page)?.[1]}</strong>
                    </div>
                    <div className="header-right">
                        <span className="connection">
                            <i /> Painel conectado
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
                                SEU SERVIDOR, SIMPLIFICADO
                            </span>
                            <h1>
                                {page === "overview"
                                    ? "Visão geral"
                                    : nav.find((n) => n[0] === page)?.[1]}
                                <span className="heading-dot">.</span>
                            </h1>
                            <p>
                                {page === "overview"
                                    ? `Olá, ${user.name.split(" ")[0]}. Vamos dar vida ao seu próximo projeto?`
                                    : descriptions[page] ||
                                      "Recursos organizados para cada conta."}
                            </p>
                        </div>
                        <button
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
                                ? "Novo projeto"
                                : "Nova configuração"}
                        </button>
                    </div>
                    {demo && (
                        <div className="notice">
                            <BookOpen size={18} />
                            <span>
                                <strong>Demonstração pública</strong> · Dados
                                fictícios e navegação em modo leitura. Nenhuma
                                operação é executada em servidores.
                            </span>
                        </div>
                    )}
                    {message && (
                        <div className="notice success">
                            <Check size={17} />
                            {message}
                        </div>
                    )}
                    {page === "overview" ? (
                        <>
                            <div className="hero">
                                <div className="hero-content">
                                    <span className="hero-label">
                                        <span /> SEU PRÓXIMO DEPLOY COMEÇA AQUI
                                    </span>
                                    <h2>
                                        Menos complexidade.
                                        <br />
                                        Mais possibilidades.
                                    </h2>
                                    <p>
                                        Sites, containers e bancos de dados.
                                        <br />
                                        Sua infraestrutura inteira, no mesmo
                                        lugar.
                                    </p>
                                    <button onClick={() => open()}>
                                        Criar meu primeiro projeto{" "}
                                        <ArrowUpRight size={17} />
                                    </button>
                                </div>
                                <div className="server-illustration">
                                    <div className="orbit orbit-one" />
                                    <div className="orbit orbit-two" />
                                    <div className="float-tag tag-top">
                                        <Globe size={16} /> seus projetos.online
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
                                        <ShieldCheck size={16} /> Seu espaço
                                        para construir
                                    </div>
                                    <span className="spark s1">+</span>
                                    <span className="spark s2">+</span>
                                </div>
                            </div>
                            <div className="metrics">
                                {[
                                    [
                                        Globe,
                                        "Projetos",
                                        projects.length,
                                        `de ${account?.project_limit || 0} disponíveis`,
                                    ],
                                    [
                                        Database,
                                        "Bancos de dados",
                                        resources.filter(
                                            (r) => r.kind === "databases",
                                        ).length,
                                        `de ${account?.database_limit || 0} disponíveis`,
                                    ],
                                    [
                                        HardDrive,
                                        "Espaço reservado",
                                        `${(used / 1024).toFixed(1)} GB`,
                                        `${(account?.disk_mb || 0) / 1024} GB no seu plano`,
                                    ],
                                    [
                                        MemoryStick,
                                        "Memória do plano",
                                        `${(account?.memory_mb || 0) / 1024} GB`,
                                        "Limite planejado por conta",
                                    ],
                                ].map(([I, label, value, sub], i) => (
                                    <div className="metric" key={label}>
                                        <div className="metric-label">
                                            {label}
                                            <I size={17} />
                                        </div>
                                        <strong>{value}</strong>
                                        <div className="metric-bottom">
                                            <span>{sub}</span>
                                            {i < 2 ? (
                                                <span className="mini-tag">
                                                    cota
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
                                                Seus projetos{" "}
                                                <span className="number">
                                                    {projects.length}
                                                </span>
                                            </h3>
                                            <p>Um lugar para cada ideia.</p>
                                        </div>
                                        <button
                                            className="text-button"
                                            onClick={() => go("sites")}
                                        >
                                            Ver todos <ArrowUpRight size={15} />
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
                                                Grandes ideias começam com um
                                                deploy.
                                            </h3>
                                            <p>
                                                Conecte seu repositório ou
                                                escolha uma stack
                                                <br />
                                                para preparar seu primeiro
                                                projeto.
                                            </p>
                                            <button
                                                className="secondary"
                                                onClick={() => open()}
                                            >
                                                <Plus size={16} /> Adicionar
                                                projeto
                                            </button>
                                        </div>
                                    )}
                                    <div className="card-footer">
                                        <GitBranch size={15} /> Seu código, seu
                                        repositório, suas regras.
                                        <button
                                            onClick={() => go("integrations")}
                                        >
                                            Conectar Git{" "}
                                            <ArrowUpRight size={14} />
                                        </button>
                                    </div>
                                </section>
                                <section className="card server-info">
                                    <div className="section-head">
                                        <div>
                                            <h3>Seu ambiente</h3>
                                            <p>Informações desta instalação</p>
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
                                                {server.platform} · PHP{" "}
                                                {server.php}
                                            </small>
                                        </div>
                                    </div>
                                    <div className="detail-row">
                                        <span>Agente Linux</span>
                                        <span className="status warning">
                                            Não conectado
                                        </span>
                                    </div>
                                    <div className="detail-row">
                                        <span>Monitoramento</span>
                                        <strong>Aguardando agente</strong>
                                    </div>
                                    <div className="detail-row">
                                        <span>Provisionamento</span>
                                        <strong>Planejamento</strong>
                                    </div>
                                    <div className="agent-note">
                                        <Activity size={17} />
                                        <span>
                                            Métricas reais de CPU, RAM e tráfego
                                            estarão disponíveis ao conectar o
                                            agente.
                                        </span>
                                    </div>
                                </section>
                            </div>
                            <section className="quick-section">
                                <div className="section-head">
                                    <div>
                                        <h3>Sua stack, do seu jeito</h3>
                                        <p>
                                            As ferramentas que você já conhece.
                                            Juntas.
                                        </p>
                                    </div>
                                    <button
                                        className="text-button"
                                        onClick={() => go("marketplace")}
                                    >
                                        Explorar marketplace{" "}
                                        <ArrowUpRight size={15} />
                                    </button>
                                </div>
                                <div className="quick-grid">
                                    {[
                                        [
                                            "N",
                                            "Nginx",
                                            "Servidor web",
                                            "services",
                                        ],
                                        [
                                            "P",
                                            "PostgreSQL",
                                            "Banco de dados",
                                            "databases",
                                        ],
                                        [
                                            "D",
                                            "Docker Compose",
                                            "Containers",
                                            "sites",
                                        ],
                                        [
                                            "L",
                                            "Laravel",
                                            "Framework PHP",
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
                    ) : page === "accounts" ? (
                        <section className="card account-card">
                            <h3>Meu plano de hospedagem</h3>
                            <p>
                                Dados isolados por conta. As cotas abaixo
                                validam a reserva de recursos.
                            </p>
                            <div className="plan-grid">
                                {[
                                    ["Projetos", account?.project_limit],
                                    [
                                        "Bancos de dados",
                                        account?.database_limit,
                                    ],
                                    ["Disco", `${account?.disk_mb / 1024} GB`],
                                    [
                                        "Memória",
                                        `${account?.memory_mb / 1024} GB`,
                                    ],
                                ].map(([k, v]) => (
                                    <div key={k}>
                                        <small>{k}</small>
                                        <strong>{v}</strong>
                                    </div>
                                ))}
                            </div>
                            <div className="notice">
                                O isolamento de CPU, memória e disco no sistema
                                operacional requer integração com o agente
                                Linux.
                            </div>
                        </section>
                    ) : page === "marketplace" ? (
                        <>
                            <div className="toolbar">
                                <div className="search">
                                    <Search size={17} />
                                    <input
                                        placeholder="Buscar aplicativos…"
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
                                    <option>Todos</option>
                                    {Object.keys(catalog).map((k) => (
                                        <option key={k} value={k}>
                                            {nav.find((n) => n[0] === k)?.[1]}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="market-grid">
                                {Object.entries(catalog)
                                    .filter(
                                        ([k]) =>
                                            filter === "Todos" || filter === k,
                                    )
                                    .flatMap(([k, apps]) =>
                                        apps
                                            .filter((a) =>
                                                a
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
                                                        Receita planejada
                                                    </span>
                                                    <h3>{a}</h3>
                                                    <p>
                                                        {
                                                            nav.find(
                                                                (n) =>
                                                                    n[0] === k,
                                                            )?.[1]
                                                        }{" "}
                                                        · Configuração por
                                                        workspace
                                                    </p>
                                                    <button
                                                        className="secondary"
                                                        onClick={() =>
                                                            open(k, a)
                                                        }
                                                    >
                                                        Configurar{" "}
                                                        <Plus size={15} />
                                                    </button>
                                                </article>
                                            )),
                                    )}
                            </div>
                            <div className="notice">
                                O catálogo apresenta opções de configuração.
                                Compatibilidade e instalação serão verificadas
                                pelo executor Linux.
                            </div>
                        </>
                    ) : (
                        <section className="card">
                            <div className="section-head">
                                <div>
                                    <h3>
                                        {nav.find((n) => n[0] === page)?.[1]}{" "}
                                        <span className="number">
                                            {visible.length}
                                        </span>
                                    </h3>
                                    <p>Configurações do seu workspace</p>
                                </div>
                                <div className="search">
                                    <Search size={16} />
                                    <input
                                        placeholder="Buscar por nome…"
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
                                                "Remover esta configuração do painel?",
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
                                            ? "Nenhum resultado encontrado"
                                            : "Seu espaço está pronto para começar."}
                                    </h3>
                                    <p>
                                        {search
                                            ? "Experimente buscar outro nome."
                                            : "Adicione uma configuração para organizar seu servidor."}
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
                                        <Plus size={16} /> Adicionar
                                        configuração
                                    </button>
                                </div>
                            )}
                            <div className="card-footer">
                                <Activity size={15} /> Configurações planejadas
                                · execução depende do agente Linux.
                            </div>
                        </section>
                    )}
                    <footer>
                        <span>
                            <span className="footer-dot" /> OpenPanel Community
                        </span>
                        <span>Feito para construir. Livre para crescer.</span>
                        <span>
                            Laravel + React + Inertia{" "}
                            <span className="version">v0.1</span>
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
                                    ADICIONAR AO WORKSPACE
                                </span>
                                <h2 id="modal-title">
                                    {nav.find((n) => n[0] === modal)?.[1]}
                                </h2>
                            </div>
                            <button
                                className="icon"
                                onClick={() => setModal(null)}
                                aria-label="Fechar"
                            >
                                <X size={22} />
                            </button>
                        </div>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (demo) {
                                    window.alert(
                                        "Demonstração em modo leitura. Instale o painel para salvar configurações.",
                                    );
                                    return;
                                }
                                f.post("/resources", {
                                    onSuccess: () => setModal(null),
                                });
                            }}
                        >
                            <label>
                                Nome
                                <input
                                    autoFocus
                                    required
                                    pattern="[a-z0-9][a-z0-9._-]*"
                                    maxLength={63}
                                    placeholder="meu-projeto"
                                    value={f.data.name}
                                    onChange={(e) =>
                                        f.setData("name", e.target.value)
                                    }
                                />
                                <small className="error">{f.errors.name}</small>
                            </label>
                            <div className="form-row">
                                <label>
                                    Stack / aplicativo
                                    <select
                                        value={f.data.engine}
                                        onChange={(e) =>
                                            f.setData("engine", e.target.value)
                                        }
                                    >
                                        {catalog[modal].map((a) => (
                                            <option key={a}>{a}</option>
                                        ))}
                                    </select>
                                    <small className="error">
                                        {f.errors.engine}
                                    </small>
                                </label>
                                <label>
                                    Reserva de disco (MB)
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
                                Domínio <small>opcional</small>
                                <input
                                    placeholder="app.seudominio.com"
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
                                Repositório HTTPS <small>opcional</small>
                                <input
                                    type="url"
                                    pattern="https://.*"
                                    placeholder="https://github.com/voce/projeto"
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
                                    Esta ação salva o planejamento. Nenhum
                                    serviço será instalado até a integração com
                                    o agente Linux.
                                </span>
                            </div>
                            <div className="modal-actions">
                                <button
                                    type="button"
                                    className="secondary"
                                    onClick={() => setModal(null)}
                                >
                                    Cancelar
                                </button>
                                <button
                                    className="primary"
                                    disabled={f.processing}
                                >
                                    <Plus size={16} />
                                    {f.processing
                                        ? "Salvando…"
                                        : "Salvar configuração"}
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
    return (
        <div className="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PROJETO / RECURSO</th>
                        <th>STACK</th>
                        <th>STATUS</th>
                        <th>DISCO</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.id}>
                            <td>
                                <strong>{r.name}</strong>
                                <small>
                                    {r.domain || "Sem domínio configurado"}
                                </small>
                            </td>
                            <td>
                                <span className="stack-label">{r.engine}</span>
                            </td>
                            <td>
                                <span className="status warning">
                                    Planejado
                                </span>
                            </td>
                            <td>{r.disk_mb} MB</td>
                            <td>
                                <button
                                    className="icon delete"
                                    aria-label={`Remover ${r.name}`}
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
if (document.getElementById("app")?.hasAttribute("data-page"))
    createInertiaApp({
        resolve: (name) => ({ Panel, Login })[name],
        setup({ el, App, props }) {
            createRoot(el).render(<App {...props} />);
        },
        progress: { color: "#25896d" },
    });
