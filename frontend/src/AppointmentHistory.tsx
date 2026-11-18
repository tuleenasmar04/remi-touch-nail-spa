import { useQuery } from "@tanstack/react-query";
import { api } from "./api";
import { ErrorMessage, useSalon } from "./App";
import { statusLabel } from "./status";
export default function AppointmentHistory({ id }: { id: number }) {
  const { t, locale, catalog } = useSalon();
  const data = useQuery({
    queryKey: ["admin", "history", id],
    queryFn: () => api(`/admin/appointments/${id}/history`),
  });
  return (
    <div className="form-card appointment-history">
      <h2>{t("history")}</h2>
      <ErrorMessage error={data.error} />
      {data.isPending && <p role="status">{t("loading")}</p>}
      <ol>
        {data.data?.events.map((event: any) => (
          <li key={event.id}>
            <strong>{statusLabel(event.status, locale)}</strong>
            <span>
              {" "}
              ·{" "}
              {new Date(
                event.created_at.endsWith("Z")
                  ? event.created_at
                  : event.created_at.replace(" ", "T") + "Z",
              ).toLocaleString(locale, {
                timeZone: catalog?.settings.timezone || "Asia/Amman",
              })}
            </span>
            {event.event === "rescheduled" && <p>{t("reschedule")}</p>}
            {event.details && <HistoryDetails value={event.details} />}
          </li>
        ))}
      </ol>
      {!data.isPending && !data.data?.events.length && <p>{t("noHistory")}</p>}
    </div>
  );
}
function HistoryDetails({ value }: { value: any }) {
  const { locale, catalog } = useSalon();
  const details = typeof value === "string" ? JSON.parse(value) : value;
  return details.from && details.to ? (
    <p>
      {new Date(details.from).toLocaleString(locale, {
        timeZone: catalog?.settings.timezone || "Asia/Amman",
      })}{" "}
      →{" "}
      {new Date(details.to).toLocaleString(locale, {
        timeZone: catalog?.settings.timezone || "Asia/Amman",
      })}
    </p>
  ) : null;
}
