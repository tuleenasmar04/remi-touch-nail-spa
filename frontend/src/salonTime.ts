export function salonInput(
  value: string | Date,
  timeZone = "Asia/Amman",
): string {
  if (
    typeof value === "string" &&
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)
  )
    return value;
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date(value));
  const fields = Object.fromEntries(parts.map((p) => [p.type, p.value]));
  return `${fields.year}-${fields.month}-${fields.day}T${fields.hour}:${fields.minute}`;
}

export function salonInstant(
  value: string,
  timeZone = "Asia/Amman",
): string | null {
  if (!value) return null;
  const local = Date.parse(value + "Z");
  if (!Number.isFinite(local)) throw Error("Invalid date");
  let guess = local;
  for (let i = 0; i < 3; i++) {
    const displayed = Date.parse(salonInput(new Date(guess), timeZone) + "Z");
    guess += local - displayed;
  }
  if (salonInput(new Date(guess), timeZone) !== value)
    throw Error("This local time does not exist");
  return new Date(guess).toISOString();
}

export function displayMoney(
  amount: number | null,
  currency: string,
  locale: string,
): string {
  if (amount === null) return "—";
  const formatter = new Intl.NumberFormat(locale, {
    style: "currency",
    currency,
  });
  return formatter.format(
    amount / 10 ** formatter.resolvedOptions().maximumFractionDigits!,
  );
}
