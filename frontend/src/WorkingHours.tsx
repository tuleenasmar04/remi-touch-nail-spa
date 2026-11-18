import { useSalon } from "./App";

export type Hours = { weekday: number; starts_at: string; ends_at: string };
export function weekdayName(day: number, locale: string): string {
  return new Intl.DateTimeFormat(locale, {
    weekday: "long",
    timeZone: "UTC",
  }).format(new Date(Date.UTC(2026, 10, 15 + day)));
}
export function clockTime(value: string, locale: string): string {
  if (!/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(value)) return "—";
  return new Intl.DateTimeFormat(locale, {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    timeZone: "UTC",
  }).format(new Date("2000-01-01T" + value.slice(0, 5) + ":00Z"));
}
export default function WorkingHours() {
  const { catalog, t, locale } = useSalon();
  const raw = catalog?.settings.working_hours;
  const hours: Hours[] = Array.isArray(raw)
    ? raw.filter(
        (row) =>
          row &&
          Number.isInteger(row.weekday) &&
          row.weekday >= 0 &&
          row.weekday <= 6 &&
          typeof row.starts_at === "string" &&
          typeof row.ends_at === "string",
      )
    : [];
  if (!hours.length) return null;
  const sameDaily =
    hours.length === 7 &&
    new Set(hours.map((row) => row.weekday)).size === 7 &&
    hours.every(
      (row) =>
        row.starts_at === hours[0].starts_at &&
        row.ends_at === hours[0].ends_at,
    );
  const timeRange = (row: Hours) => (
    <bdi>
      {clockTime(row.starts_at, locale)} – {clockTime(row.ends_at, locale)}
    </bdi>
  );
  return (
    <div className="working-hours">
      <p>
        <strong>{t("workingHours")}</strong>
      </p>
      {sameDaily ? (
        <p>
          {t("everyDay")}
          <br />
          {timeRange(hours[0])}
        </p>
      ) : (
        [1, 2, 3, 4, 5, 6, 0].map((day) => (
          <p key={day}>
            {weekdayName(day, locale)}:{" "}
            {hours.filter((row) => row.weekday === day).length
              ? hours
                  .filter((row) => row.weekday === day)
                  .map((row, index) => (
                    <span key={index}> {timeRange(row)}</span>
                  ))
              : t("closedDay")}
          </p>
        ))
      )}
      <p>
        <bdi dir="ltr">{catalog?.settings.timezone}</bdi>
      </p>
    </div>
  );
}
