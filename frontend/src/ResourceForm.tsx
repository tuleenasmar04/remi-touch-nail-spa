import { useState } from "react";
import type { FormEvent } from "react";
import { useQuery } from "@tanstack/react-query";
import { X, Plus } from "lucide-react";
import { api } from "./api";
import { Field, useSalon } from "./App";
import { relationOptions } from "./relationOptions";
import { moneyToMinor } from "./money";
import { salonInput, salonInstant } from "./salonTime";

const specs: Record<string, Record<string, string>> = {
  services: {
    name: "localized",
    description: "localized",
    category_id: "category",
    price_minor: "money",
    duration_minutes: "number",
    buffer_before: "number",
    buffer_after: "number",
    published: "checkbox",
    bookable: "checkbox",
    image_url: "text",
  },
  categories: {
    name: "localized",
    sort_order: "number",
    published: "checkbox",
  },
  team: {
    name: "localized",
    service_ids: "serviceMultiple",
    published: "checkbox",
  },
  schedule: {
    weekday: "weekday",
    starts_at: "time",
    ends_at: "time",
  },
  blocks: {
    starts_at: "datetime",
    ends_at: "datetime",
    reason: "text",
    kind: "kind",
  },
  bookings: {
    service_id: "service",
    starts_at: "datetime",
    name: "text",
    email: "email",
    phone: "text",
    status: "status",
    internal_notes: "textarea",
  },
  courses: {
    title: "localized",
    description: "localized",
    syllabus: "localized",
    image_url: "text",
    published: "checkbox",
  },
  offerings: {
    course_id: "course",
    capacity: "number",
    price_minor: "money",
    registration_opens_at: "datetime",
    registration_closes_at: "datetime",
    cancellation_hours: "number",
    sessions: "sessions",
    published: "checkbox",
  },
  registrations: { status: "registrationStatus" },
  content: { key: "contentKey", value: "localized", published: "checkbox" },
  gallery: {
    image_url: "text",
    alt: "localized",
    caption: "localized",
    category_id: "category",
    published: "checkbox",
  },
  settings: { key: "settingKey", value: "settingValue" },
};
const labels: Record<string, [string, string]> = {
  name: ["Name", "الاسم"],
  email: ["Email address", "البريد الإلكتروني"],
  phone: ["Phone", "الهاتف"],
  category_id: ["Category", "الفئة"],
  price_minor: ["Price", "السعر"],
  duration_minutes: ["Duration (minutes)", "المدة بالدقائق"],
  buffer_before: [
    "Preparation before (minutes)",
    "التحضير قبل الموعد بالدقائق",
  ],
  buffer_after: ["Cleanup after (minutes)", "التجهيز بعد الموعد بالدقائق"],
  staff_ids: ["Eligible professionals", "الأخصائيات المؤهلات"],
  service_ids: ["Offered services", "الخدمات المقدمة"],
  staff_id: ["Professional", "الأخصائية"],
  service_id: ["Service", "الخدمة"],
  weekday: ["Day of week", "يوم الأسبوع"],
  starts_at: ["Start (salon time)", "البداية (بتوقيت الصالون)"],
  ends_at: ["End (salon time)", "النهاية (بتوقيت الصالون)"],
  published: ["Published", "منشور"],
  bookable: ["Available for online booking", "متاح للحجز الإلكتروني"],
  image_url: ["Image address", "رابط الصورة"],
  sort_order: ["Display order", "ترتيب العرض"],
  reason: ["Reason", "السبب"],
  kind: ["Availability type", "نوع التوفر"],
  description: ["Description", "الوصف"],
  title: ["Title", "العنوان"],
  syllabus: ["Course syllabus", "منهج الدورة"],
  capacity: ["Places", "عدد الأماكن"],
  registration_opens_at: [
    "Registration opens (salon time)",
    "فتح التسجيل (بتوقيت الصالون)",
  ],
  registration_closes_at: [
    "Registration closes (salon time)",
    "إغلاق التسجيل (بتوقيت الصالون)",
  ],
  cancellation_hours: ["Cancellation notice (hours)", "مهلة الإلغاء بالساعات"],
  sessions: ["Teaching sessions", "الجلسات التدريبية"],
  course_id: ["Course", "الدورة"],
  key: ["Content or setting", "المحتوى أو الإعداد"],
  value: ["Content", "المحتوى"],
  alt: ["Image description for accessibility", "وصف الصورة لإمكانية الوصول"],
  caption: ["Caption", "وصف الصورة"],
  internal_notes: ["Internal notes", "ملاحظات داخلية"],
  status: ["Status", "الحالة"],
};
const settingLabels: Record<string, [string, string]> = {
  salon_name: ["Salon name", "اسم الصالون"],
  logo_url: ["Logo image address", "رابط الشعار"],
  hero_image_url: ["Hero image address", "رابط صورة الرئيسية"],
  timezone: ["Salon timezone", "المنطقة الزمنية للصالون"],
  currency: ["Currency code", "رمز العملة"],
  country: ["Country", "الدولة"],
  address: ["Address", "العنوان"],
  phone: ["Phone", "الهاتف"],
  email: ["Email", "البريد الإلكتروني"],
  directions_url: ["Directions link", "رابط الاتجاهات"],
  instagram_url: ["Instagram link", "رابط إنستغرام"],
  facebook_url: ["Facebook link", "رابط فيسبوك"],
  booking_enabled: ["Enable online booking", "تفعيل الحجز الإلكتروني"],
  require_approval: [
    "Require owner approval for bookings",
    "تتطلب الحجوزات موافقة المالكة",
  ],
  slot_interval_minutes: [
    "Time between offered slots (minutes)",
    "الفاصل بين المواعيد المعروضة بالدقائق",
  ],
  minimum_notice_minutes: [
    "Minimum notice (minutes)",
    "الحد الأدنى للإشعار بالدقائق",
  ],
  booking_horizon_days: [
    "Days ahead customers may book",
    "عدد الأيام المتاحة للحجز مقدمًا",
  ],
  cancellation_hours: ["Cancellation cutoff (hours)", "مهلة الإلغاء بالساعات"],
  reschedule_hours: [
    "Rescheduling cutoff (hours)",
    "مهلة تغيير الموعد بالساعات",
  ],
};
const contentLabels: Record<string, [string, string]> = {
  footer_story: ["Footer story", "قصتنا في التذييل"],
  hero_title: ["Homepage heading", "عنوان الرئيسية"],
  hero_description: ["Homepage description", "وصف الرئيسية"],
  introduction: ["Salon introduction", "نبذة عن الصالون"],
  privacy: ["Privacy policy", "سياسة الخصوصية"],
  policy: ["Booking policy", "سياسة الحجز"],
  seo_title: ["Search title", "عنوان نتائج البحث"],
  seo_description: ["Search description", "وصف نتائج البحث"],
};
async function options(resource: string) {
  let page = 1;
  const all: any[] = [];
  while (true) {
    const result = await api(`/admin/${resource}?page=${page}`);
    if (!Array.isArray(result?.data))
      throw new Error("Invalid relationship data");
    all.push(...result.data);
    if (!Number.isSafeInteger(result.last_page) || page >= result.last_page)
      break;
    page++;
  }
  return all;
}
export default function ResourceForm({
  area,
  initial,
  onSave,
  onClose,
}: {
  area: string;
  initial: any;
  onSave: (value: any) => void;
  onClose: () => void;
}) {
  const { t, locale, catalog } = useSalon();
  const [error, setError] = useState("");
  const [upload, setUpload] = useState("");
  const [relations, setRelations] = useState<Record<string, string | string[]>>(
    {},
  );
  const [setting, setSetting] = useState(initial.key || "salon_name");
  const [sessions, setSessions] = useState<any[]>(
    initial.sessions || [{ starts_at: "", ends_at: "", staff_id: null }],
  );
  const arabic = locale === "ar";
  const label = (key: string) => (labels[key] || [key, key])[arabic ? 1 : 0];
  const needed = new Set(Object.values(specs[area] || {}));
  const staff = useQuery({
    queryKey: ["form-options", "staff"],
    queryFn: () => options("staff"),
    enabled: [...needed].some((x) => x.startsWith("staff")),
  });
  const services = useQuery({
    queryKey: ["form-options", "services"],
    queryFn: () => options("services"),
    enabled: [...needed].some((x) => x.startsWith("service")),
  });
  const categories = useQuery({
    queryKey: ["form-options", "categories"],
    queryFn: () => options("categories"),
    enabled: needed.has("category"),
  });
  const categoryPending = area === "services" && categories.isPending;
  const courses = useQuery({
    queryKey: ["form-options", "courses"],
    queryFn: () => options("courses"),
    enabled: needed.has("course"),
  });
  const currency = catalog?.settings.currency;
  const digits = currency
    ? new Intl.NumberFormat("en", {
        style: "currency",
        currency,
      }).resolvedOptions().maximumFractionDigits!
    : 0;
  const dateValue = (value: any) =>
    value ? salonInput(value, catalog?.settings.timezone || "Asia/Amman") : "";
  const iso = (value: string) =>
    salonInstant(value, catalog?.settings.timezone || "Asia/Amman");
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (categoryPending) return;
    setError("");
    try {
      const fd = new FormData(event.currentTarget);
      const result: any = {};
      for (const [key, type] of Object.entries(specs[area] || {})) {
        const value = String(fd.get(key) || "");
        if (type === "localized")
          result[key] = {
            en: String(fd.get(key + ".en") || ""),
            ar: String(fd.get(key + ".ar") || ""),
          };
        else if (type === "checkbox") result[key] = fd.has(key);
        else if (type.endsWith("Multiple"))
          result[key] = fd.getAll(key).map(Number);
        else if (
          type === "number" ||
          [
            "category",
            "staff",
            "staffOptional",
            "service",
            "course",
            "weekday",
          ].includes(type)
        )
          result[key] = value === "" ? null : Number(value);
        else if (type === "money") result[key] = moneyToMinor(value, digits);
        else if (type === "datetime") result[key] = iso(value);
        else if (type === "sessions")
          result[key] = sessions.map((s) => ({
            starts_at: iso(
              s.starts_at.length === 16 ? s.starts_at : dateValue(s.starts_at),
            ),
            ends_at: iso(
              s.ends_at.length === 16 ? s.ends_at : dateValue(s.ends_at),
            ),
            staff_id: s.staff_id ? Number(s.staff_id) : null,
          }));
        else if (type === "settingValue")
          result[key] = ["booking_enabled", "require_approval"].includes(
            setting,
          )
            ? value === "true"
            : [
                  "slot_interval_minutes",
                  "minimum_notice_minutes",
                  "booking_horizon_days",
                  "cancellation_hours",
                  "reschedule_hours",
                ].includes(setting)
              ? Number(value)
              : value;
        else result[key] = value;
      }
      if (upload) {
        if (area === "settings") result.value = upload;
        else result.image_url = upload;
      }
      await onSave(result);
    } catch {
      setError(
        arabic
          ? "تحققي من السعر والتواريخ المدخلة."
          : "Please check the entered price and dates.",
      );
    }
  }
  function relation(type: string, key: string) {
    const multiple = type.endsWith("Multiple");
    const query = type.startsWith("staff")
      ? staff
      : type.startsWith("service")
        ? services
        : type === "category"
          ? categories
          : courses;
    const records = relationOptions(query.data, locale);
    const selected =
      relations[key] ??
      (multiple
        ? Array.isArray(initial[key])
          ? initial[key].map(String)
          : []
        : String(initial[key] ?? ""));
    const validIds = new Set(records.map((record) => record.id));
    return (
      <select
        name={key}
        multiple={multiple}
        disabled={query.isPending}
        aria-busy={query.isPending}
        aria-invalid={Boolean(query.error)}
        required={area === "services" && type === "category"}
        value={
          multiple
            ? Array.isArray(selected)
              ? selected.filter((id) => validIds.has(id))
              : []
            : typeof selected === "string" && validIds.has(selected)
              ? selected
              : ""
        }
        onChange={(event) => {
          const value = multiple
            ? Array.from(
                event.currentTarget.selectedOptions,
                (option) => option.value,
              )
            : event.currentTarget.value;
          setRelations((current) => ({ ...current, [key]: value }));
        }}
        size={
          multiple ? Math.min(5, Math.max(3, records?.length || 3)) : undefined
        }
      >
        <option value="" disabled={multiple}>
          {query.isPending
            ? t("loading")
            : query.error
              ? t("unavailable")
              : type === "staffOptional"
                ? t("anyStaff")
                : "—"}
        </option>
        {records.map((r) => (
          <option key={r.id} value={r.id}>
            {r.label}
          </option>
        ))}
      </select>
    );
  }
  return (
    <form className="form-card resource-form" onSubmit={submit}>
      <div className="section-heading">
        <h2>{t(initial.id ? "edit" : "create")}</h2>
        <button
          type="button"
          className="icon-button"
          onClick={onClose}
          aria-label={t("close")}
        >
          <X />
        </button>
      </div>
      {Object.entries(specs[area] || {}).map(([key, type]) => (
        <div key={key}>
          {type === "localized" ? (
            <fieldset>
              <legend>{label(key)}</legend>
              {(["en", "ar"] as const).map((lang) => (
                <Field key={lang} label={lang === "en" ? "English" : "العربية"}>
                  <textarea
                    name={key + "." + lang}
                    defaultValue={initial[key]?.[lang] || ""}
                    dir={lang === "ar" ? "rtl" : "ltr"}
                  />
                </Field>
              ))}
            </fieldset>
          ) : type === "sessions" ? (
            <fieldset>
              <legend>{label(key)}</legend>
              {sessions.map((s, i) => (
                <div className="session-row" key={i}>
                  <Field label={label("starts_at")}>
                    <input
                      type="datetime-local"
                      required
                      value={dateValue(s.starts_at)}
                      onChange={(e) =>
                        setSessions(
                          sessions.map((v, j) =>
                            j === i ? { ...v, starts_at: e.target.value } : v,
                          ),
                        )
                      }
                    />
                  </Field>
                  <Field label={label("ends_at")}>
                    <input
                      type="datetime-local"
                      required
                      value={dateValue(s.ends_at)}
                      onChange={(e) =>
                        setSessions(
                          sessions.map((v, j) =>
                            j === i ? { ...v, ends_at: e.target.value } : v,
                          ),
                        )
                      }
                    />
                  </Field>

                  <button
                    type="button"
                    className="text-link"
                    disabled={sessions.length === 1}
                    onClick={() =>
                      setSessions(sessions.filter((_, j) => i !== j))
                    }
                  >
                    {t("archive")}
                  </button>
                </div>
              ))}
              <button
                className="text-link"
                type="button"
                onClick={() =>
                  setSessions([
                    ...sessions,
                    { starts_at: "", ends_at: "", staff_id: null },
                  ])
                }
              >
                <Plus size={15} />
                {t("create")}
              </button>
            </fieldset>
          ) : (
            <Field
              label={
                type === "money"
                  ? `${label(key)} (${currency || (arabic ? "أصغر وحدة للعملة" : "minor units")})`
                  : label(key)
              }
            >
              {[
                "category",
                "staff",
                "staffOptional",
                "service",
                "course",
                "staffMultiple",
                "serviceMultiple",
              ].includes(type) ? (
                relation(type, key)
              ) : type === "weekday" ? (
                <select name={key} defaultValue={initial[key] ?? 1}>
                  {Array.from({ length: 7 }, (_, i) => (
                    <option key={i} value={i}>
                      {new Intl.DateTimeFormat(locale, {
                        weekday: "long",
                        timeZone: "UTC",
                      }).format(new Date(Date.UTC(2026, 10, 15 + i)))}
                    </option>
                  ))}
                </select>
              ) : type === "kind" ? (
                <select name={key} defaultValue={initial[key] || "block"}>
                  <option value="block">
                    {arabic ? "وقت غير متاح" : "Unavailable block"}
                  </option>
                  <option value="override">
                    {arabic ? "وقت إضافي متاح" : "Additional availability"}
                  </option>
                </select>
              ) : type === "status" || type === "registrationStatus" ? (
                <select name={key} defaultValue={initial[key] || "confirmed"}>
                  {(type === "registrationStatus"
                    ? [
                        "pending",
                        "confirmed",
                        "completed",
                        "cancelled",
                        "no_show",
                      ]
                    : [
                        "pending",
                        "confirmed",
                        "completed",
                        "cancelled",
                        "no_show",
                      ]
                  ).map((s) => (
                    <option key={s} value={s}>
                      {t(
                        (
                          {
                            pending: "pendingStatus",
                            confirmed: "confirmedStatus",
                            completed: "completedStatus",
                            cancelled: "cancelledStatus",
                            no_show: "noShowStatus",
                          } as const
                        )[s as "confirmed"],
                      )}
                    </option>
                  ))}
                </select>
              ) : type === "contentKey" ? (
                <select name={key} defaultValue={initial[key] || "hero_title"}>
                  {Object.entries(contentLabels).map(([k, l]) => (
                    <option value={k} key={k}>
                      {l[arabic ? 1 : 0]}
                    </option>
                  ))}
                </select>
              ) : type === "settingKey" ? (
                <select
                  name={key}
                  value={setting}
                  onChange={(e) => setSetting(e.target.value)}
                  disabled={Boolean(initial.id)}
                >
                  {Object.entries(settingLabels).map(([k, l]) => (
                    <option value={k} key={k}>
                      {l[arabic ? 1 : 0]}
                    </option>
                  ))}
                </select>
              ) : type === "settingValue" ? (
                ["booking_enabled", "require_approval"].includes(setting) ? (
                  <select
                    key={setting}
                    name={key}
                    defaultValue={String(
                      initial.value === true ||
                        initial.value === 1 ||
                        initial.value === "1",
                    )}
                  >
                    <option value="false">
                      {setting === "require_approval"
                        ? arabic
                          ? "تأكيد تلقائي"
                          : "Automatic confirmation"
                        : arabic
                          ? "مغلق"
                          : "Closed"}
                    </option>
                    <option value="true">
                      {setting === "require_approval"
                        ? arabic
                          ? "موافقة المالكة مطلوبة"
                          : "Owner approval required"
                        : arabic
                          ? "مفتوح"
                          : "Open"}
                    </option>
                  </select>
                ) : setting === "timezone" ? (
                  <select name={key} defaultValue={initial.value || "UTC"}>
                    {["UTC", ...Intl.supportedValuesOf("timeZone")].map(
                      (zone) => (
                        <option key={zone}>{zone}</option>
                      ),
                    )}
                  </select>
                ) : (
                  <input name={key} defaultValue={initial.value ?? ""} />
                )
              ) : type === "textarea" ? (
                <textarea name={key} defaultValue={initial[key] || ""} />
              ) : (
                <input
                  name={key}
                  type={
                    type === "datetime"
                      ? "datetime-local"
                      : type === "money"
                        ? "number"
                        : type
                  }
                  min={type === "number" || type === "money" ? 0 : undefined}
                  step={type === "money" ? String(1 / 10 ** digits) : undefined}
                  defaultChecked={
                    type === "checkbox" ? Boolean(initial[key]) : undefined
                  }
                  defaultValue={
                    type === "checkbox"
                      ? undefined
                      : type === "time"
                        ? String(initial[key] || "").slice(0, 5)
                        : type === "datetime"
                          ? dateValue(initial[key])
                          : type === "time"
                            ? String(initial[key] || "").slice(0, 5)
                            : type === "money"
                              ? initial[key] === null ||
                                initial[key] === undefined
                                ? ""
                                : initial[key] / 10 ** digits
                              : (initial[key] ??
                                initial.customer?.[key] ??
                                (type === "number" ? 0 : ""))
                  }
                />
              )}
            </Field>
          )}
        </div>
      ))}
      {area === "settings" && initial.id && (
        <input type="hidden" name="key" value={setting} />
      )}
      <p className="muted">
        {arabic
          ? "جميع التواريخ والأوقات بتوقيت الصالون: " +
            (catalog?.settings.timezone || "Asia/Amman")
          : "All dates and times use the salon timezone: " +
            (catalog?.settings.timezone || "Asia/Amman")}
      </p>
      {(["services", "gallery", "courses"].includes(area) ||
        (area === "settings" &&
          ["logo_url", "hero_image_url"].includes(setting))) && (
        <Field label={t("image")}>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={async (e) => {
              if (!e.target.files?.[0]) return;
              const fd = new FormData();
              fd.append("image", e.target.files[0]);
              try {
                const result = await api("/admin/uploads", {
                  method: "POST",
                  body: fd,
                });
                setUpload(result.url);
              } catch {
                setError(t("unavailable"));
              }
            }}
          />
        </Field>
      )}
      {upload && <img className="upload-preview" src={upload} alt="" />}
      {error && <p role="alert">{error}</p>}
      <button className="button" disabled={categoryPending}>
        {t("save")}
      </button>
    </form>
  );
}
