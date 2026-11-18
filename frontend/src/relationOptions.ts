export function relationOptions(
  records: unknown,
  locale: "en" | "ar",
): { id: string; label: string }[] {
  if (!Array.isArray(records)) return [];
  const seen = new Set<string>();
  return records.flatMap((record) => {
    if (!record || typeof record !== "object") return [];
    if (typeof record.id !== "number" && typeof record.id !== "string")
      return [];
    const id = Number(record.id);
    if (!Number.isSafeInteger(id) || id <= 0 || seen.has(String(id))) return [];
    seen.add(String(id));
    const name = record.name ?? record.title;
    const label =
      typeof name === "string"
        ? name
        : name && typeof name === "object"
          ? [name[locale], name.en, name.ar].find(
              (value) => typeof value === "string" && value.trim(),
            )
          : undefined;
    return [{ id: String(id), label: label || "#" + id }];
  });
}
