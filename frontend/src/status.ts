export function statusLabel(value: string, locale: string): string {
  if (locale !== "ar") return value;
  return (
    (
      {
        pending: "قيد الانتظار",
        confirmed: "مؤكد",
        cancelled: "ملغى",
        completed: "مكتمل",
        no_show: "لم تحضر",
        queued: "في قائمة الإرسال",
        stored: "محفوظة",
        sent: "تم الإرسال",
        logged: "محفوظة محليًا",
        failed: "تعذّر الإرسال",
      } as Record<string, string>
    )[value] || value
  );
}
