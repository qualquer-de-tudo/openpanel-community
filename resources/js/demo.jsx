import React from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { Panel } from "./app.jsx";
import catalog from "../data/catalog.json";
import { I18nProvider } from "./i18n.jsx";
const resources = [
    {
        id: 1,
        kind: "sites",
        name: "meu-portfolio",
        engine: "React",
        domain: "portfolio.example.com",
        disk_mb: 512,
        status: "planned",
    },
    {
        id: 2,
        kind: "sites",
        name: "minha-api",
        engine: "Laravel",
        domain: "api.example.com",
        disk_mb: 1024,
        status: "planned",
    },
    {
        id: 3,
        kind: "databases",
        name: "app-postgres",
        engine: "PostgreSQL",
        domain: null,
        disk_mb: 1024,
        status: "planned",
    },
    {
        id: 4,
        kind: "sites",
        name: "stack-docker",
        engine: "Docker Compose",
        domain: null,
        disk_mb: 512,
        status: "planned",
    },
    {
        id: 5,
        kind: "security",
        name: "antivirus-uploads",
        engine: "ClamAV",
        domain: null,
        disk_mb: 256,
        status: "planned",
    },
];
const demoRelease = __QOPENPANEL_TEMPLATES__.find(
    (item) => item.id === "static-site" && item.version === "1.1.0",
);
const oldDemoRelease = __QOPENPANEL_TEMPLATES__.find(
    (item) => item.id === "static-site" && item.version === "1.0.0",
);
resources.push({
    id: 6,
    kind: "sites",
    name: "landing-template",
    engine: "Static",
    domain: "landing.example.com",
    disk_mb: 256,
    status: "planned",
});
const templateInstallations = [
    {
        installation: {
            id: "demo-installation",
            resource_id: 6,
            template_id: "static-site",
            version: "1.1.0",
            digest: demoRelease.digest,
        },
        resource: { id: 6, name: "landing-template", revision: 2 },
        history: [
            {
                id: "demo-update",
                sequence: 2,
                action: "update",
                version: "1.1.0",
                digest: demoRelease.digest,
                project_version_id: "22222222-2222-4222-8222-222222222222",
            },
            {
                id: "demo-initial",
                sequence: 1,
                action: "install",
                version: "1.0.0",
                digest: oldDemoRelease.digest,
                project_version_id: "11111111-1111-4111-8111-111111111111",
            },
        ],
    },
];
createInertiaApp({
    page: {
        component: "Panel",
        props: {
            demo: true,
            resources,
            catalog,
            templates: __QOPENPANEL_TEMPLATES__,
            templateInstallations,
            account: {
                disk_mb: 20480,
                memory_mb: 4096,
                project_limit: 10,
                database_limit: 5,
            },
            user: { name: "Visitante", email: "visitante@example.com" },
            server: {
                hostname: "servidor-demo",
                platform: "Linux (ilustrativo)",
                php: "8.5",
                agent: false,
            },
        },
        url: window.location.pathname,
        version: "demo",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => Panel,
    setup({ el, App, props }) {
        createRoot(el).render(
            <I18nProvider>
                <App {...props} />
            </I18nProvider>,
        );
    },
});
