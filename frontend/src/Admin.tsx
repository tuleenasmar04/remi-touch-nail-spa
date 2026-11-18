import ResourceForm from "./ResourceForm";
import AppointmentCalendar from "./AppointmentCalendar";
import AppointmentHistory from "./AppointmentHistory";
import OwnerAccount from "./OwnerAccount";
import { displayMoney } from "./salonTime";
import { notifySalonChange } from "./salonUpdates";
import { weekdayName, clockTime } from "./WorkingHours";
import Logo from "./Logo";
import { statusLabel } from "./status";
import { useState, useEffect } from "react";
import type { FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowUpRight,
  ArrowRight,
  CalendarDays,
  LockKeyhole,
  LogOut,
  ChevronRight,
} from "lucide-react";
import { api, csrf, mutation, ApiError } from "./api";
import { client, useSalon, Field, ErrorMessage, PageTitle } from "./App";
import type { MessageKey } from "./i18n";
const resources = [
  "services",
  "categories",
  "calendar",
  "account",
  "schedule",
  "blocks",
  "bookings",
  "courses",
  "offerings",
  "registrations",
  "content",
  "gallery",
  "settings",
  "audit",
  "inbox",
] as const;
const resourcePaths: Record<string, string> = {
  team: "staff",
  schedule: "working-hours",
  blocks: "schedule-blocks",
  bookings: "appointments",
  offerings: "course-offerings",
  content: "content-blocks",
  settings: "settings",
  audit: "audit-logs",
  inbox: "contact-messages",
};
export default function Admin() {
  const { t, locale, text, catalog } = useSalon();
  const current = useLocation();
  const navigate = useNavigate();
  const auth = useQuery({
    queryKey: ["admin-me"],
    queryFn: () => api("/admin/me"),
    retry: false,
  });
  const [error, setError] = useState<unknown>();
  const [area, setArea] = useState("dashboard");
  const [editing, setEditing] = useState<any>();
  const [notice, setNotice] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const params = new URLSearchParams(location.search);
  const [mode, setMode] = useState(params.get("token") ? "reset" : "login");
  const path = resourcePaths[area] || area;
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [status, setStatus] = useState("");
  const [service, setService] = useState("");
  useEffect(() => {
    if (auth.isPending) return;
    if (!auth.data && !current.pathname.endsWith("/login"))
      navigate(`/${locale}/admin/login${current.search}`, { replace: true });
    if (auth.data && current.pathname.endsWith("/login"))
      navigate(`/${locale}/admin`, { replace: true });
  }, [auth.isPending, auth.data, current.pathname, locale]);
  const records = useQuery({
    queryKey: ["admin", area, search, page, from, to, status, service],
    queryFn: () =>
      api(
        `/admin/${area === "dashboard" ? "statistics" : path}?search=${encodeURIComponent(search)}&page=${page}${from ? "&from=" + from : ""}${to ? "&to=" + to : ""}${area === "bookings" && status ? "&status=" + status : ""}${area === "bookings" && service ? "&service_id=" + service : ""}`,
      ),
    enabled: Boolean(auth.data) && !["calendar", "account"].includes(area),
    refetchInterval: ["dashboard", "bookings"].includes(area) ? 15000 : false,
  });
  async function signIn(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    const data = Object.fromEntries(new FormData(e.currentTarget));
    try {
      await csrf();
      if (mode === "login") {
        await mutation("/auth/login", data);
        await auth.refetch();
      } else {
        await mutation(
          mode === "reset" ? "/auth/reset-password" : "/auth/forgot-password",
          {
            ...data,
            token: params.get("token"),
            password_confirmation: data.password,
            locale,
          },
        );
        setNotice(t("recoverySent"));
        if (mode === "reset") setMode("login");
      }
    } catch (err) {
      setError(err);
    }
  }
  async function save(data: any) {
    setError(null);
    try {
      await mutation(
        `/admin/${path}${editing?.id ? "/" + editing.id : ""}`,
        data,
        editing?.id ? "PUT" : "POST",
      );
      await client.invalidateQueries({ queryKey: ["admin"] });
      await client.invalidateQueries({ queryKey: ["catalog"] });
      await client.invalidateQueries({ queryKey: ["form-options"] });
      await client.invalidateQueries({ queryKey: ["availability"] });
      setEditing(undefined);
      setNotice(t("saved"));
      notifySalonChange();
    } catch (err) {
      setError(err);
    }
  }
  if (auth.isPending)
    return (
      <p className="empty" role="status">
        {t("loading")}
      </p>
    );
  if (!auth.data)
    return (
      <section className="section narrow">
        <div className="login-brand">
          <Logo />
        </div>
        <PageTitle title={t(mode === "login" ? "login" : "reset")} />
        <form className="form-card" onSubmit={signIn}>
          <LockKeyhole size={28} />
          <Field label={t("email")}>
            <input
              name="email"
              type="email"
              required
              autoComplete="username"
              defaultValue={params.get("email") || ""}
            />
          </Field>
          {mode !== "forgot" && (
            <Field label={t("password")}>
              <input
                name="password"
                type="password"
                required
                autoComplete={
                  mode === "login" ? "current-password" : "new-password"
                }
                minLength={mode === "reset" ? 12 : 1}
              />
            </Field>
          )}
          <ErrorMessage
            error={
              error ||
              (auth.error instanceof ApiError && auth.error.status !== 401
                ? auth.error
                : null)
            }
          />
          {notice && <p role="status">{notice}</p>}
          <button className="button">
            {t(mode === "login" ? "signIn" : "send")}
          </button>
          <button
            type="button"
            className="text-link back"
            onClick={() => setMode(mode === "login" ? "forgot" : "login")}
          >
            {t(mode === "login" ? "forgot" : "login")}
          </button>
        </form>
      </section>
    );
  return (
    <section className="admin-shell">
      <aside className="admin-nav">
        <div className="admin-brand">
          <Logo />
        </div>
        {["dashboard", ...resources].map((r) => (
          <button
            className={r === area ? "active" : ""}
            key={r}
            onClick={() => {
              setArea(r);
              setEditing(undefined);
              setPage(1);
              setSearch("");
              setError(null);
              setNotice("");
            }}
          >
            {t(r as MessageKey)}
            <ChevronRight size={14} />
          </button>
        ))}
        <button
          onClick={async () => {
            await mutation("/auth/logout", {});
            client.removeQueries({ queryKey: ["admin"] });
            client.setQueryData(["admin-me"], null);
            auth.refetch();
          }}
        >
          <LogOut size={16} />
          {t("logout")}
        </button>
      </aside>
      <div className="admin-main">
        <div className="section-heading">
          <div>
            <span className="eyebrow">
              {t("welcome")}, {auth.data.name}
            </span>
            <h1>{t(area as MessageKey)}</h1>
          </div>
          <Link className="text-link" to={`/${locale}`}>
            {t("viewSite")}
            <ArrowUpRight size={16} />
          </Link>
        </div>
        <ErrorMessage error={error || records.error} />
        {notice && (
          <p className="alert" role="status">
            {notice}
          </p>
        )}
        {["dashboard", "bookings"].includes(area) && (
          <div className="admin-filters">
            <Field label={t("fromDate")}>
              <input
                type="date"
                value={from}
                onChange={(e) => {
                  setFrom(e.target.value);
                  setPage(1);
                }}
              />
            </Field>
            <Field label={t("toDate")}>
              <input
                type="date"
                value={to}
                min={from || undefined}
                onChange={(e) => {
                  setTo(e.target.value);
                  setPage(1);
                }}
              />
            </Field>
            {area === "bookings" && (
              <>
                <Field label={t("status")}>
                  <select
                    value={status}
                    onChange={(e) => {
                      setStatus(e.target.value);
                      setPage(1);
                    }}
                  >
                    <option value="">{t("allStatuses")}</option>
                    {[
                      "pending",
                      "confirmed",
                      "completed",
                      "cancelled",
                      "no_show",
                    ].map((s) => (
                      <option key={s} value={s}>
                        {statusLabel(s, locale)}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label={t("services")}>
                  <select
                    value={service}
                    onChange={(e) => {
                      setService(e.target.value);
                      setPage(1);
                    }}
                  >
                    <option value="">{t("all")}</option>
                    {catalog?.services.map((s) => (
                      <option key={s.id} value={s.id}>
                        {text(s.name)}
                      </option>
                    ))}
                  </select>
                </Field>
              </>
            )}
          </div>
        )}
        {records.isFetching && <p role="status">{t("loading")}</p>}
        {area === "calendar" ? (
          <AppointmentCalendar
            onEdit={(record) => {
              setArea("bookings");
              setEditing(record);
            }}
            onCreate={(record) => {
              setArea("bookings");
              setEditing(record);
            }}
            onBlock={(record) => {
              setArea("blocks");
              setEditing(record);
            }}
          />
        ) : area === "account" ? (
          <OwnerAccount
            onChanged={() => {
              client.removeQueries({ queryKey: ["admin"] });
              client.setQueryData(["admin-me"], null);
              setNotice(t("passwordChanged"));
              auth.refetch();
            }}
          />
        ) : area === "dashboard" ? (
          <>
            <div className="stats-grid">
              {Object.entries(records.data?.data || {}).map(([k, v]) => (
                <div className="stat" key={k}>
                  <span>
                    {(
                      {
                        today:
                          locale === "ar"
                            ? "مواعيد اليوم"
                            : "Today’s appointments",
                        upcoming:
                          locale === "ar"
                            ? "مواعيد قادمة"
                            : "Upcoming appointments",
                        cancelled: locale === "ar" ? "ملغاة" : "Cancelled",
                        registrations: t("registrations"),
                      } as Record<string, string>
                    )[k] ||
                      (
                        {
                          total: t("total"),
                          pending: t("pending"),
                          confirmed: t("confirmedCount"),
                          completed: t("completedCount"),
                          no_show: t("noShowCount"),
                        } as Record<string, string>
                      )[k] ||
                      k}
                  </span>
                  <strong>{String(v)}</strong>
                </div>
              ))}
            </div>
            <div className="form-card">
              <h2>{t("estimatedRevenue")}</h2>
              {records.data?.estimated_revenue?.length ? (
                records.data.estimated_revenue.map((item: any) => (
                  <p className="revenue-value" key={item.currency}>
                    {displayMoney(item.amount_minor, item.currency, locale)}
                  </p>
                ))
              ) : (
                <p>
                  {displayMoney(0, catalog?.settings.currency || "JOD", locale)}
                </p>
              )}
              <p className="muted">{t("estimatedRevenueNote")}</p>
            </div>
            <div className="form-card">
              <CalendarDays size={30} />
              <h2>{t("bookings")}</h2>
              <p>{t("notice")}</p>
              <button className="text-link" onClick={() => setArea("bookings")}>
                {t("bookings")}
                <ArrowRight size={16} />
              </button>
            </div>
          </>
        ) : (
          <>
            <div className="table-tools">
              <Field label={t("search")}>
                <input
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    setPage(1);
                  }}
                />
              </Field>
              {!["audit", "inbox", "registrations"].includes(area) && (
                <button className="button" onClick={() => setEditing({})}>
                  {t("create")}
                </button>
              )}
            </div>
            {editing !== undefined && (
              <ResourceForm
                key={area + ":" + (editing.id ?? "new")}
                area={area}
                initial={editing}
                onSave={save}
                onClose={() => setEditing(undefined)}
              />
            )}
            {area === "bookings" && editing?.id && (
              <AppointmentHistory id={editing.id} />
            )}
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>{t("name")}</th>
                    <th>{t("status")}</th>
                    <th>{t("actions")}</th>
                  </tr>
                </thead>
                <tbody>
                  {records.data?.data?.map((r: any) => (
                    <tr key={r.id}>
                      <td>{r.id}</td>
                      <td>
                        {area === "schedule"
                          ? weekdayName(r.weekday, locale)
                          : text(r.name || r.title) ||
                            r.reference ||
                            r.key ||
                            r.email ||
                            r.action ||
                            r.staff_id}
                        {area === "inbox" && (
                          <details>
                            <summary>{t("message")}</summary>
                            <p className="prose">{r.message}</p>
                            <a href={"mailto:" + r.email}>{r.email}</a>
                          </details>
                        )}
                        {r.customer && (
                          <small>
                            {r.customer.name} ·{" "}
                            <bdi dir="ltr">{r.customer.phone}</bdi>
                          </small>
                        )}
                        {area === "schedule" && (
                          <small>
                            <bdi>
                              {clockTime(r.starts_at, locale)} –{" "}
                              {clockTime(r.ends_at, locale)}
                            </bdi>
                          </small>
                        )}
                        {r.starts_at && area !== "schedule" && (
                          <small>
                            {new Date(r.starts_at).toLocaleString(locale, {
                              timeZone:
                                catalog?.settings.timezone || "Asia/Amman",
                            })}
                          </small>
                        )}
                      </td>
                      <td>
                        {statusLabel(
                          r.status || r.delivery_status || "",
                          locale,
                        ) ||
                          r.delivery_status ||
                          (r.published !== undefined
                            ? r.published
                              ? "●"
                              : "○"
                            : "")}
                      </td>
                      <td>
                        {!["audit", "inbox"].includes(area) && (
                          <button
                            className="text-link"
                            onClick={() => setEditing(r)}
                          >
                            {t("edit")}
                          </button>
                        )}
                        {![
                          "audit",
                          "inbox",
                          "settings",
                          "bookings",
                          "registrations",
                        ].includes(area) && (
                          <button
                            className="text-link"
                            onClick={async () => {
                              try {
                                await api(`/admin/${path}/${r.id}`, {
                                  method: "DELETE",
                                });
                                records.refetch();
                                client.invalidateQueries({
                                  queryKey: ["catalog"],
                                });
                              } catch (err) {
                                setError(err);
                              }
                            }}
                          >
                            {t("archive")}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!records.data?.data?.length && (
              <p className="empty">{t("noRecords")}</p>
            )}
            <div className="pagination">
              <button
                className="pill"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                {t("back")}
              </button>
              <span>
                {page} / {records.data?.last_page || 1}
              </span>
              <button
                className="pill"
                disabled={page >= (records.data?.last_page || 1)}
                onClick={() => setPage(page + 1)}
              >
                {t("next")}
              </button>
            </div>
          </>
        )}
      </div>
    </section>
  );
}
