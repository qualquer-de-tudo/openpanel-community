import React, { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import { useI18n } from "./i18n.jsx";
import { panelUrl } from "./panel-url.mjs";
import release from "../data/release.json";

export default function Maintenance({ data, demo }) {
    const { t, locale } = useI18n();
    const [channel, setChannel] = useState("stable");
    const [releases, setReleases] = useState([]);
    const [loading, setLoading] = useState(false);
    const [checked, setChecked] = useState(false);
    const [error, setError] = useState(false);
    const update = useForm({ action: "update", version: "", channel: "stable", password: "", acknowledge: false });
    const scan = useForm({ action: "scan" });
    const connected = !demo && data?.connected;
    const security = data?.security;
    const date = (timestamp) => timestamp ? new Date(timestamp * 1000).toLocaleString(locale.replace("_", "-")) : t("Não verificado");
    async function check() {
        if (demo) return;
        setLoading(true);
        setError(false);
        try {
            const response = await fetch(panelUrl(`/maintenance/releases?channel=${channel}`), { headers: { Accept: "application/json" }, credentials: "same-origin", signal: AbortSignal.timeout(15000) });
            if (!response.ok) throw new Error();
            const result = await response.json();
            setReleases(result.releases);
            setChecked(true);
            update.setData({ ...update.data, version: "", channel });
        } catch { setError(true); }
        finally { setLoading(false); }
    }
    const statuses = { running: t("Em execução"), completed: t("Concluído"), failed: t("Falhou") };
    const severities = { CRITICAL: t("Críticas"), HIGH: t("Altas"), MEDIUM: t("Médias"), LOW: t("Baixas"), UNKNOWN: t("Não classificadas") };
    return <div className="maintenance-grid">
        <div className="notice">{t("Somente operadores do servidor podem solicitar manutenção. O painel não recebe acesso ao socket Docker.")}</div>
        <section className="card maintenance-card">
            <div className="section-title"><h3>{t("Atualizações do painel")}</h3><span className="badge">{release.version}</span></div>
            <p>{connected ? t("Agente conectado") : t("Agente indisponível ou ocupado")}</p>
            <p>{t("A atualização cria backup, valida a versão oficial e testa a aplicação antes de publicar as portas.")}</p>
            <div className="maintenance-actions">
                <select aria-label={t("Canal de versões")} disabled={loading} value={channel} onChange={(e) => { setChannel(e.target.value); setReleases([]); setChecked(false); update.reset(); }}>
                    <option value="stable">{t("Estável")}</option><option value="beta">{t("Incluir versões beta")}</option>
                </select>
                <button className="secondary" disabled={demo || loading} onClick={check}>{loading ? t("Consultando...") : t("Buscar versões")}</button>
                <button className="secondary" disabled={demo} onClick={() => router.reload({ only: ["maintenance"] })}>{t("Atualizar status")}</button>
            </div>
            {error && <p className="error">{t("Não foi possível consultar as versões oficiais.")}</p>}
            <form onSubmit={(event) => { event.preventDefault(); update.post(panelUrl("/maintenance/request"), { preserveScroll: true, onFinish: () => update.reset("password") }); }}>
                <label>{t("Versão de destino")}<select required value={update.data.version} onChange={(e) => update.setData("version", e.target.value)}>
                    <option value="">{t("Escolha uma versão")}</option>
                    {releases.filter((item) => item.newer).map((item) => <option key={item.tag} value={item.tag}>{item.tag}</option>)}
                </select></label>
                {checked && !releases.some((item) => item.newer) && <p>{t("Nenhuma versão mais recente neste canal.")}</p>}
                {releases.filter((item) => item.tag === update.data.version).map((item) => <a key={item.tag} href={item.url} target="_blank" rel="noreferrer">{t("Notas da versão")}</a>)}
                <label>{t("Senha")}<input type="password" required maxLength={1024} autoComplete="current-password" value={update.data.password} onChange={(e) => update.setData("password", e.target.value)} /></label>
                <label className="maintenance-ack"><input type="checkbox" required checked={update.data.acknowledge} onChange={(e) => update.setData("acknowledge", e.target.checked)} />{t("Li as notas e autorizo a janela de manutenção e a atualização para a versão selecionada.")}</label>
                <p>{t("Falhas anteriores à publicação restauram o backup. Depois da publicação, uma recuperação pode exigir intervenção do operador.")}</p>
                {Object.entries(update.errors).map(([key, value]) => <p className="error" key={key}>{value}</p>)}
                <button className="primary" disabled={!connected || !update.data.version || update.processing}>{t("Agendar atualização")}</button>
            </form>
        </section>
        <section className="card maintenance-card">
            <div className="section-title"><h3>{t("Segurança do servidor")}</h3><button className="secondary" disabled={!connected || scan.processing} onClick={() => scan.post(panelUrl("/maintenance/request"), { preserveScroll: true })}>{t("Executar verificação")}</button></div>
            <p>{t("A verificação cobre CVEs de pacotes do sistema operacional e informações de SSH. Não corrige pacotes automaticamente.")}</p>
            <p>{t("Última verificação")}: {date(security?.timestamp)}</p>
            <div className="maintenance-counts">{["CRITICAL", "HIGH", "MEDIUM", "LOW", "UNKNOWN"].map((level) => <div key={level}><small>{severities[level]}</small><strong>{security?.counts?.[level] ?? "—"}</strong></div>)}</div>
            {Object.entries(scan.errors).map(([key, value]) => <p className="error" key={key}>{value}</p>)}
            {!security && <p>{t("Nenhuma verificação executada. Ausência de relatório não significa servidor seguro.")}</p>}
            {security?.eosl && <p className="error">{t("O sistema operacional está fora do período de suporte informado pelo scanner.")}</p>}
            {security?.timestamp && Date.now() / 1000 - security.timestamp > 86400 && <p className="error">{t("O relatório tem mais de 24 horas. Execute uma nova verificação.")}</p>}
            {security?.reboot_required && <p>{t("O sistema indica necessidade de reinicialização.")}</p>}
            {security?.ssh && <dl>{["permitrootlogin", "passwordauthentication", "pubkeyauthentication"].map((key) => <div key={key}><dt>{key}</dt><dd><code>{security.ssh[key]}</code></dd></div>)}</dl>}
            {security?.findings?.length > 0 && <div className="table-scroll"><table><thead><tr><th>{t("Vulnerabilidade")}</th><th>{t("Pacote")}</th><th>{t("Instalada")}</th><th>{t("Correção disponível")}</th></tr></thead><tbody>{security.findings.slice(0, 100).map((item, index) => <tr key={`${item.id}-${index}`}><td><code>{item.id}</code><small>{item.severity}</small></td><td>{item.package}</td><td>{item.installed}</td><td>{item.fixed || t("Não informada")}</td></tr>)}</tbody></table></div>}
            {(security?.truncated || security?.findings?.length > 100) && <p>{t("Relatório resumido. Nem todos os resultados aparecem nesta lista.")}</p>}
        </section>
        <section className="card maintenance-card"><h3>{t("Histórico de manutenção")}</h3>
            <p>{t("Pedidos são processados pelo agente. Atualize o status para acompanhar o resultado.")}</p>
            <div className="table-scroll"><table><thead><tr><th>{t("Ação")}</th><th>{t("Versão")}</th><th>{t("Status")}</th></tr></thead><tbody>{(data?.jobs || []).map((job) => <tr key={job.id}><td>{job.action === "update" ? t("Atualização") : t("Verificação de segurança")}<small>{job.id.slice(0, 8)}</small></td><td>{job.target || "—"}</td><td>{statuses[job.result?.status] || t("Na fila")}<small>{job.result?.code}</small></td></tr>)}</tbody></table></div>
        </section>
    </div>;
}
