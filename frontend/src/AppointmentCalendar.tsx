import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronLeft, ChevronRight, CalendarDays } from "lucide-react";
import { api } from "./api";
import { ErrorMessage, Field, useSalon } from "./App";
import { salonInput, salonInstant } from "./salonTime";
import { statusLabel } from "./status";

const offsetDay = (day: string, offset: number) => {
  const date = new Date(day + "T12:00:00Z");
  date.setUTCDate(date.getUTCDate() + offset);
  return date.toISOString().slice(0, 10);
};
export default function AppointmentCalendar({
  onEdit,
  onCreate,
  onBlock,
}: {
  onEdit: (record: any) => void;
  onCreate: (record: any) => void;
  onBlock: (record: any) => void;
}) {
  const { t, text, locale, catalog } = useSalon();
  const zone = catalog?.settings.timezone || "Asia/Amman";
  const [anchor, setAnchor] = useState(() =>
    salonInput(new Date(), zone).slice(0, 10),
  );
  const [selected, setSelected] = useState(anchor);
  const [view, setView] = useState<"day" | "week" | "month">("week");
  const [service, setService] = useState("");
  const weekday = new Date(anchor + "T12:00:00Z").getUTCDay();
  const first = anchor.slice(0, 7) + "-01";
  const start =
    view === "day"
      ? anchor
      : view === "week"
        ? offsetDay(anchor, -weekday)
        : offsetDay(first, -new Date(first + "T12:00:00Z").getUTCDay());
  const count = view === "day" ? 1 : view === "week" ? 7 : 42;
  const end = offsetDay(start, count - 1);
  const data = useQuery({
    queryKey: ["admin", "calendar", start, end],
    queryFn: () => api(`/admin/calendar?from=${start}&to=${end}`),
    refetchInterval: 30000,
  });
  const slots = useQuery({
    queryKey: ["admin", "availability", service, selected],
    queryFn: () =>
      api(`/admin/availability?service_id=${service}&date=${selected}`),
    enabled: Boolean(service),
    refetchInterval: 20000,
  });
  const events: any[] = data.data?.appointments || [];
  const active = (date: string) =>
    events.filter((e) => salonInput(e.starts_at, zone).startsWith(date));
  function move(direction: number) {
    const day =
      view === "month"
        ? offsetDay(first, direction > 0 ? 32 : -1).slice(0, 7) + "-01"
        : offsetDay(anchor, direction * (view === "week" ? 7 : 1));
    setAnchor(day);
    setSelected(day);
  }
  return (
    <div className="appointment-calendar">
      <div className="calendar-toolbar">
        <div className="calendar-views" aria-label={t("calendar")}>
          {(["day", "week", "month"] as const).map((v) => (
            <button
              type="button"
              key={v}
              className={"pill " + (view === v ? "active" : "")}
              aria-pressed={view === v}
              onClick={() => setView(v)}
            >
              {t(
                v === "day"
                  ? "dayView"
                  : v === "week"
                    ? "weekView"
                    : "monthView",
              )}
            </button>
          ))}
        </div>
        <div className="calendar-date">
          <button
            className="icon-button"
            aria-label={t("previousPeriod")}
            onClick={() => move(-1)}
          >
            <ChevronLeft />
          </button>
          <Field label={t("date")}>
            <input
              type="date"
              value={anchor}
              onChange={(e) => {
                if (e.target.value) {
                  setAnchor(e.target.value);
                  setSelected(e.target.value);
                }
              }}
            />
          </Field>
          <button
            className="icon-button"
            aria-label={t("nextPeriod")}
            onClick={() => move(1)}
          >
            <ChevronRight />
          </button>
        </div>
      </div>
      <p className="muted">
        {t("salonTimezone")}: <bdi dir="ltr">{zone}</bdi>
      </p>
      <ErrorMessage error={data.error} />
      {data.isFetching && <p role="status">{t("loading")}</p>}
      <div className="calendar-scroll">
        <div className={"calendar-grid calendar-" + view}>
          {Array.from({ length: count }, (_, i) => offsetDay(start, i)).map(
            (day) => {
              const dayEvents = active(day);
              return (
                <button
                  key={day}
                  className={
                    "calendar-cell " + (day === selected ? "selected" : "")
                  }
                  aria-pressed={day === selected}
                  onClick={() => setSelected(day)}
                >
                  <span className="calendar-day-heading">
                    {new Intl.DateTimeFormat(locale, {
                      weekday: "short",
                      month: "short",
                      day: "numeric",
                      timeZone: "UTC",
                    }).format(new Date(day + "T12:00:00Z"))}
                  </span>
                  {dayEvents.slice(0, 3).map((event) => (
                    <span
                      key={event.id}
                      className={"calendar-event " + event.status}
                    >
                      <bdi>
                        {new Intl.DateTimeFormat(locale, {
                          hour: "2-digit",
                          minute: "2-digit",
                          timeZone: zone,
                        }).format(new Date(event.starts_at))}
                      </bdi>{" "}
                      {text(event.service_name)}
                    </span>
                  ))}
                  {dayEvents.length > 3 && <span>+{dayEvents.length - 3}</span>}
                  {!dayEvents.length && (
                    <span className="calendar-empty">—</span>
                  )}
                </button>
              );
            },
          )}
        </div>
      </div>
      <div className="calendar-detail form-card">
        <div className="section-heading">
          <div>
            <span className="eyebrow">{selected}</span>
            <h2>{t("dayAppointments")}</h2>
          </div>
          <button
            className="pill"
            onClick={() =>
              onBlock({
                starts_at: salonInstant(selected + "T09:00", zone),
                ends_at: salonInstant(selected + "T10:00", zone),
                kind: "block",
              })
            }
          >
            {t("blockTime")}
          </button>
        </div>
        {active(selected).length ? (
          active(selected).map((event) => (
            <div className="calendar-appointment" key={event.id}>
              <div>
                <strong>{text(event.service_name)}</strong>
                <p>
                  {event.customer?.name} ·{" "}
                  <bdi dir="ltr">{event.customer?.phone}</bdi>
                </p>
                <small>
                  {new Date(event.starts_at).toLocaleTimeString(locale, {
                    timeZone: zone,
                    hour: "2-digit",
                    minute: "2-digit",
                  })}{" "}
                  · {statusLabel(event.status, locale)}
                </small>
              </div>
              <button className="text-link" onClick={() => onEdit(event)}>
                {t("edit")}
              </button>
            </div>
          ))
        ) : (
          <p>{t("noRecords")}</p>
        )}
        <Field label={t("services")}>
          <select value={service} onChange={(e) => setService(e.target.value)}>
            <option value="">{t("chooseService")}</option>
            {catalog?.services
              .filter((s) => s.bookable)
              .map((s) => (
                <option key={s.id} value={s.id}>
                  {text(s.name)}
                </option>
              ))}
          </select>
        </Field>
        <ErrorMessage error={slots.error} />
        {slots.isFetching && <p role="status">{t("loading")}</p>}
        <div className="slots">
          {slots.data?.slots.map((slot: any) => (
            <button
              className="slot"
              key={slot.starts_at}
              disabled={!slot.available}
              aria-label={
                t(slot.available ? "reserveTime" : "booked") +
                " " +
                new Date(slot.starts_at).toLocaleTimeString(locale, {
                  timeZone: zone,
                  hour: "2-digit",
                  minute: "2-digit",
                })
              }
              onClick={() =>
                onCreate({
                  service_id: Number(service),
                  starts_at: slot.starts_at,
                  status: "confirmed",
                })
              }
            >
              <span>
                {new Date(slot.starts_at).toLocaleTimeString(locale, {
                  timeZone: zone,
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
              <small>
                {t(
                  slot.available
                    ? "available"
                    : slot.reason === "booked"
                      ? "booked"
                      : "blocked",
                )}
              </small>
            </button>
          ))}
        </div>
        {service && !slots.isFetching && !slots.data?.slots.length && (
          <p>{t("noSlots")}</p>
        )}
        {data.data?.blocks
          .filter(
            (block: any) =>
              salonInput(block.starts_at, zone).slice(0, 10) <= selected &&
              salonInput(block.ends_at, zone).slice(0, 10) >= selected,
          )
          .map((block: any) => (
            <p className="alert" key={block.id}>
              {t("blocked")}: {block.reason || "—"} ·{" "}
              {new Date(block.starts_at).toLocaleString(locale, {
                timeZone: zone,
              })}
            </p>
          ))}
        <span className="muted">
          <CalendarDays size={14} aria-hidden="true" /> {t("activeBookings")}:{" "}
          {
            active(selected).filter((e) =>
              ["pending", "confirmed"].includes(e.status),
            ).length
          }
        </span>
      </div>
    </div>
  );
}
