import {
  cloneElement,
  createContext,
  useContext,
  useEffect,
  useId,
  useRef,
  useState,
  lazy,
  Suspense,
} from "react";
import type { FormEvent, ReactElement, ReactNode } from "react";
import {
  BrowserRouter,
  Link,
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";
import {
  QueryClient,
  QueryClientProvider,
  useQuery,
} from "@tanstack/react-query";
import {
  ArrowUpRight,
  ArrowRight,
  Menu,
  X,
  Sparkles,
  MapPin,
  Phone,
  Flower2,
  Check,
} from "lucide-react";
import { api, csrf, mutation, ApiError } from "./api";
import type { Catalog, Localized } from "./api";
import { messages } from "./i18n";
import type { Locale, MessageKey } from "./i18n";
import "./style.css";
import { statusLabel } from "./status";
import Logo from "./Logo";
import SocialIcon from "./SocialIcon";
import { displayMoney } from "./salonTime";
import { notifySalonChange } from "./salonUpdates";
import WorkingHours from "./WorkingHours";

export const client = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: true } },
});
const Context = createContext<{
  locale: Locale;
  t: (key: MessageKey) => string;
  text: (value?: Localized | string) => string;
  catalog?: Catalog;
}>({ locale: "en", t: (k) => messages.en[k], text: () => "" });
export const useSalon = () => useContext(Context);
const useCatalog = () =>
  useQuery({
    queryKey: ["catalog"],
    queryFn: () => api<Catalog>("/public/catalog"),
    refetchInterval: 10000,
  });
export function ErrorMessage({ error }: { error: unknown }) {
  const { t } = useSalon();
  return error ? (
    <div role="alert" className="alert error">
      {error instanceof ApiError ? error.message : t("unavailable")}
      {error instanceof ApiError &&
        Object.values(error.errors)
          .flat()
          .map((e, i) => <p key={i}>{e}</p>)}
    </div>
  ) : null;
}
export function Field({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  const id = useId();
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      {cloneElement(children as ReactElement<{ id?: string }>, { id })}
    </div>
  );
}
function Money({ amount }: { amount: number | null }) {
  const { t, locale, catalog } = useSalon();
  const currency = catalog?.settings.currency;
  return (
    <>
      {amount === null || !currency
        ? t("quote")
        : new Intl.NumberFormat(locale, { style: "currency", currency }).format(
            amount /
              10 **
                new Intl.NumberFormat("en", {
                  style: "currency",
                  currency,
                }).resolvedOptions().maximumFractionDigits!,
          )}
    </>
  );
}
export function PageTitle({
  eyebrow,
  title,
  children,
}: {
  eyebrow?: string;
  title: string;
  children?: ReactNode;
}) {
  return (
    <div className="page-title">
      <span className="eyebrow">{eyebrow || "REMI NAIL SPA"}</span>
      <h1>{title}</h1>
      {children}
    </div>
  );
}
function Layout() {
  const params = useParams();
  const locale: Locale = params.locale === "ar" ? "ar" : "en";
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const query = useCatalog();
  const t = (key: MessageKey) => messages[locale][key];
  const text = (v?: Localized | string) =>
    typeof v === "string" ? v : v?.[locale] || v?.en || "";
  useEffect(() => {
    if (typeof BroadcastChannel === "undefined") return;
    const channel = new BroadcastChannel("remi-salon-updates");
    channel.onmessage = (event) => {
      if (event.data !== "refresh") return;
      for (const key of ["catalog", "availability", "admin"]) {
        void client.invalidateQueries({ queryKey: [key] });
      }
    };
    return () => channel.close();
  }, []);
  useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === "ar" ? "rtl" : "ltr";
    const pageKey = location.pathname.split("/")[2] || "home";
    const titleKey =
      (
        {
          book: "book",
          manage: "manage",
          confirmation: "confirmed",
          policy: "policy",
          privacy: "privacy",
          admin: "admin",
          about: "about",
          services: "services",
          gallery: "gallery",
          courses: "courses",
          contact: "contact",
        } as Record<string, MessageKey>
      )[pageKey] || "home";
    document.title = `${t(titleKey)} · REMI Nail Spa`;
  }, [locale, location.pathname]);
  useEffect(() => {
    setOpen(false);
    window.scrollTo(0, 0);
  }, [location.pathname]);
  const settings = query.data?.settings;
  const phone = settings?.phone ?? "";
  const phoneDigits = phone.replace(/\D/g, "");
  const link = (path: string) => `/${locale}${path}`;
  return (
    <Context.Provider value={{ locale, t, text, catalog: query.data }}>
      <a className="skip-link" href="#main">
        {locale === "ar" ? "انتقلي إلى المحتوى" : "Skip to content"}
      </a>
      <div className="announcement">
        {t("scroll")}
        <span>✦</span> REMI NAIL SPA
      </div>
      {query.data?.settings.demo_mode && (
        <div className="demo-banner" role="status">
          {locale === "ar"
            ? "نسخة تجريبية — الأسعار والمواعيد والفريق بيانات وهمية للتطوير."
            : "DEVELOPMENT DEMO — prices, staff, courses, and availability are fictional."}
        </div>
      )}
      <header className="header">
        <Link to={link("")} className="brand" aria-label="REMI Nail Spa">
          <Logo />
        </Link>
        <nav aria-label={t("menu")} className={open ? "nav open" : "nav"}>
          {(
            ["services", "about", "gallery", "courses", "contact"] as const
          ).map((key) => (
            <Link
              key={key}
              to={link("/" + key)}
              aria-current={
                location.pathname.endsWith(key) ? "page" : undefined
              }
            >
              {t(key)}
            </Link>
          ))}
        </nav>
        <div className="header-actions">
          <Link
            className="language"
            to={
              location.pathname.replace(
                /^\/(en|ar)/,
                locale === "en" ? "/ar" : "/en",
              ) +
              location.search +
              location.hash
            }
            lang={locale === "en" ? "ar" : "en"}
          >
            {locale === "en" ? "العربية" : "EN"}
          </Link>
          <Link className="button header-book" to={link("/book")}>
            {t("book")}
            <ArrowUpRight size={15} />
          </Link>
          <button
            className="menu-button icon-button"
            aria-label={open ? t("close") : t("menu")}
            aria-expanded={open}
            onClick={() => setOpen(!open)}
          >
            {open ? <X /> : <Menu />}
          </button>
        </div>
      </header>
      <main id="main">
        <Routes>
          <Route index element={<Home />} />
          <Route path="services" element={<Services />} />
          <Route path="services/:id" element={<ServiceDetail />} />
          <Route path="about" element={<About />} />
          <Route path="gallery" element={<Gallery />} />
          <Route path="courses" element={<Courses />} />
          <Route path="courses/:id" element={<CourseDetail />} />
          <Route path="book" element={<Booking />} />
          <Route path="confirmation" element={<Confirmation />} />
          <Route path="manage" element={<Manage />} />
          <Route path="contact" element={<Contact />} />
          <Route path="privacy" element={<Policy type="privacy" />} />
          <Route path="policy" element={<Policy type="policy" />} />
          <Route
            path="admin/*"
            element={
              <Suspense fallback={<p className="empty">{t("loading")}</p>}>
                <Admin />
              </Suspense>
            }
          />
          <Route
            path="*"
            element={
              <PageTitle title="404">
                <Link to={link("")}>{t("home")}</Link>
              </PageTitle>
            }
          />
        </Routes>
      </main>
      <footer className="salon-footer">
        <div className="footer-top">
          <Link className="brand footer-brand" to={link("")}>
            <Logo />
          </Link>
          <h2>{t("footer")}</h2>
          <Link
            to={link("/book")}
            className="round-link"
            aria-label={t("book")}
          >
            <ArrowUpRight />
          </Link>
        </div>
        <div className="footer-grid">
          <section
            className="footer-story"
            aria-labelledby="footer-story-heading"
          >
            <h3 id="footer-story-heading">{t("footerStoryHeading")}</h3>
            <p>{text(query.data?.content.footer_story) || t("footerStory")}</p>
          </section>
          <section aria-labelledby="footer-visit-heading">
            <h3 id="footer-visit-heading">{t("footerVisitHeading")}</h3>
            {settings?.address && (
              <address className="footer-address">
                <MapPin size={18} strokeWidth={1.5} aria-hidden="true" />
                <span>{settings.address}</span>
              </address>
            )}
            {settings?.directions_url && (
              <a
                className="footer-link footer-directions"
                href={settings.directions_url}
                target="_blank"
                rel="noopener noreferrer"
              >
                {t("footerDirections")}
                <ArrowUpRight size={16} strokeWidth={1.5} aria-hidden="true" />
              </a>
            )}
            <WorkingHours />
          </section>
          {(settings?.instagram_url || settings?.facebook_url) && (
            <section aria-labelledby="footer-find-heading">
              <h3 id="footer-find-heading">{t("footerFindHeading")}</h3>
              <div className="footer-link-list">
                {settings?.instagram_url && (
                  <a
                    className="footer-link footer-social-link"
                    href={settings.instagram_url}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <SocialIcon name="instagram" />
                    {t("footerInstagram")}
                  </a>
                )}
                {settings?.facebook_url && (
                  <a
                    className="footer-link footer-social-link"
                    href={settings.facebook_url}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <SocialIcon name="facebook" />
                    {t("footerFacebook")}
                  </a>
                )}
              </div>
            </section>
          )}
          {phoneDigits && (
            <section aria-labelledby="footer-contact-heading">
              <h3 id="footer-contact-heading">{t("footerContactHeading")}</h3>
              <div className="footer-link-list">
                <a
                  className="footer-link footer-phone"
                  href={"tel:+" + phoneDigits}
                  aria-label={t("footerPhoneLabel")}
                >
                  <Phone size={18} strokeWidth={1.5} aria-hidden="true" />
                  <bdi dir="ltr">{phone}</bdi>
                </a>
                <a
                  className="footer-link footer-social-link"
                  href={"https://wa.me/" + phoneDigits}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={t("footerWhatsAppLabel")}
                >
                  <SocialIcon name="whatsapp" />
                  {t("footerWhatsApp")}
                </a>
              </div>
            </section>
          )}
        </div>
        <div className="footer-bottom">
          <span>{t("footerCopyright")}</span>
          <div>
            <Link to={link("/privacy")}>{t("privacy")}</Link>
            <Link to={link("/policy")}>{t("policy")}</Link>
            <Link to={link("/admin")}>{t("admin")}</Link>
          </div>
        </div>
      </footer>
    </Context.Provider>
  );
}
function Home() {
  const { t, text, locale, catalog } = useSalon();
  return (
    <>
      <section className="hero">
        <div className="hero-copy">
          <div className="eyebrow">
            <span className="line" />
            {t("eyebrow")}
          </div>
          <h1>{text(catalog?.content.hero_title) || t("hero")}</h1>
          <p>{text(catalog?.content.hero_description) || t("intro")}</p>
          <div className="hero-cta">
            <Link className="button" to={`/${locale}/book`}>
              {t("book")}
              <ArrowUpRight size={18} />
            </Link>
            <Link className="text-link" to={`/${locale}/services`}>
              {t("explore")}
              <ArrowRight size={17} />
            </Link>
          </div>
          <div className="hero-note">
            <Flower2 size={26} strokeWidth={1} />
            <span>
              {t("scroll")}
              <small>
                {locale === "ar"
                  ? "أظافر · جمال · عناية بالنفس"
                  : "NAILS · BEAUTY · SELF-CARE"}
              </small>
            </span>
          </div>
        </div>
        <div className="hero-visual">
          {catalog?.settings.hero_image_url ? (
            <img src={catalog.settings.hero_image_url} alt="" fetchPriority="high" />
          ) : (
            <div className="portfolio-art" aria-hidden="true"><Flower2 strokeWidth={0.6} /></div>
          )}
          <div className="photo-label">
            {locale === "ar"
              ? "فن الشعور بالجمال"
              : "THE ART OF FEELING BEAUTIFUL"}
          </div>
          <div className="hero-stamp">
            <Sparkles size={20} />
            <span>
              {locale === "ar" ? "صُنعت من أجل" : "made for"}
              <br />
              <em>{locale === "ar" ? "لحظتكِ." : "your moment."}</em>
            </span>
          </div>

        </div>
      </section>
      <div className="marquee" aria-hidden="true">
        NAILS <span>✦</span> BEAUTY <span>✦</span> A LITTLE ME TIME{" "}
        <span>✦</span> REMI NAIL SPA <span>✦</span> NAILS <span>✦</span> BEAUTY
      </div>
      <section className="section">
        <div className="section-heading">
          <div>
            <span className="eyebrow">01 / {t("services")}</span>
            <h2>{t("signature")}</h2>
            <p>{t("serviceIntro")}</p>
          </div>
          <Link className="text-link" to={`/${locale}/services`}>
            {t("all")}
            <ArrowUpRight size={18} />
          </Link>
        </div>
        <div className="category-grid">
          {(["nails", "makeup", "lashes", "hair"] as const).map((c, i) => (
            <Link
              key={c}
              to={`/${locale}/services?category=${c}`}
              className={"category-card category-" + i}
            >
              <span className="category-no">0{i + 1}</span>
              <div className="category-art">
                <Flower2 strokeWidth={0.7} />
              </div>
              <div>
                <h3>{t(c)}</h3>
                <ArrowUpRight size={22} />
              </div>
            </Link>
          ))}
        </div>
      </section>
      <section className="editorial section">
        <div className="editorial-image">
          <div className="portfolio-art" aria-hidden="true"><Flower2 strokeWidth={0.6} /></div>

        </div>
        <div className="editorial-copy">
          <Flower2 size={46} strokeWidth={0.7} />
          <span className="eyebrow">02 / {t("about")}</span>
          <h2>{t("editorial")}</h2>
          <p>{text(catalog?.content.introduction) || t("editorialText")}</p>
          <Link className="text-link" to={`/${locale}/about`}>
            {t("about")}
            <ArrowUpRight size={18} />
          </Link>
        </div>
      </section>
      <section className="academy section">
        <div>
          <span className="eyebrow">03 / REMI ACADEMY</span>
          <h2>{t("academy")}</h2>
          <p>{t("academyText")}</p>
          <Link className="button" to={`/${locale}/courses`}>
            {t("viewCourses")}
            <ArrowUpRight size={18} />
          </Link>
        </div>
        <div className="academy-art" aria-hidden="true">
          <Flower2 strokeWidth={0.45} />
          <span>
            {locale === "ar" ? "تعلّمي. أبدعي. تألّقي." : "learn. create. bloom."}
          </span>
        </div>
      </section>
    </>
  );
}
function Services() {
  const { t, text, locale, catalog } = useSalon();
  const query = useCatalog();
  const [filter, setFilter] = useState(
    new URLSearchParams(location.search).get("category") || "all",
  );
  const slugs = ["nails", "makeup", "lashes", "hair"];
  return (
    <section className="section">
      <PageTitle title={t("services")}>
        <p>{t("serviceIntro")}</p>
      </PageTitle>
      <div className="filters">
        {["all", ...slugs].map((c) => (
          <button
            className={filter === c ? "pill active" : "pill"}
            onClick={() => setFilter(c)}
            key={c}
          >
            {t(c === "all" ? "all" : (c as MessageKey))}
          </button>
        ))}
      </div>
      <ErrorMessage error={query.error} />
      {query.isPending ? (
        <p role="status">{t("loading")}</p>
      ) : (
        <div className="service-grid">
          {catalog?.services
            .filter(
              (s) =>
                filter === "all" ||
                catalog.categories
                  .find((c) => c.id === s.category_id)
                  ?.name.en.toLowerCase()
                  .includes(
                    filter === "lashes"
                      ? "lash"
                      : filter === "nails"
                        ? "nail"
                        : filter,
                  ),
            )
            .map((s) => (
              <article className="service-card" key={s.id}>
                {s.image_url && (
                  <img src={s.image_url} alt={text(s.name)} loading="lazy" />
                )}
                <Flower2 className="service-flower" size={32} strokeWidth={1} />
                <h2>{text(s.name)}</h2>
                <p>{text(s.description)}</p>
                <div className="service-meta">
                  <Money amount={s.price_minor} />
                  {s.duration_minutes && (
                    <span>
                      {s.duration_minutes} {t("minutes")}
                    </span>
                  )}
                </div>
                <Link className="text-link" to={`/${locale}/services/${s.id}`}>
                  {t("discover")}
                  <ArrowUpRight size={17} />
                </Link>
              </article>
            ))}
        </div>
      )}
      {!query.isPending && !catalog?.services.length && (
        <p className="empty">{t("emptyServices")}</p>
      )}
    </section>
  );
}
function ServiceDetail() {
  const { id } = useParams();
  const { catalog, text, locale, t } = useSalon();
  const s = catalog?.services.find((s) => s.id === Number(id));
  return (
    <section className="section narrow">
      <PageTitle title={s ? text(s.name) : t("services")} />
      <p>{s ? text(s.description) : t("emptyServices")}</p>
      {s && (
        <>
          <p>
            <Money amount={s.price_minor} /> · {s.duration_minutes}{" "}
            {t("minutes")}
          </p>
          {s.bookable && (
            <Link className="button" to={`/${locale}/book?service=${s.id}`}>
              {t("book")}
              <ArrowUpRight size={18} />
            </Link>
          )}
        </>
      )}
    </section>
  );
}
function About() {
  const { t, text, catalog } = useSalon();
  return (
    <section className="section narrow">
      <PageTitle title={t("about")} />
      <Flower2 className="ornament" size={72} strokeWidth={0.6} />
      <p className="lead">
        {text(catalog?.content.introduction) || t("aboutText")}
      </p>
      {catalog?.staff.map((s) => (
        <p key={s.id}>{text(s.name)}</p>
      ))}
    </section>
  );
}
function Gallery() {
  const { t, catalog, text } = useSalon();
  const [selected, setSelected] = useState<any>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    if (selected) dialog.current?.showModal();
  }, [selected]);
  return (
    <section className="section">
      <PageTitle title={t("gallery")} />
      <div className="gallery-grid">
        {catalog?.gallery.map((item) => (
          <button
            key={item.id}
            onClick={() => setSelected(item)}
            className="gallery-item"
          >
            <img src={item.image_url} alt={text(item.alt)} loading="lazy" />
            <span>{text(item.caption)}</span>
          </button>
        ))}
      </div>
      {!catalog?.gallery.length && <p className="empty">{t("galleryEmpty")}</p>}
      <dialog ref={dialog} onClose={() => setSelected(null)}>
        <button
          className="icon-button dialog-close"
          onClick={() => dialog.current?.close()}
          aria-label={t("close")}
        >
          <X />
        </button>
        {selected && (
          <img
            className="lightbox-image"
            src={selected.image_url}
            alt={text(selected.alt)}
          />
        )}
      </dialog>
    </section>
  );
}
function Courses() {
  const { t, catalog, text, locale } = useSalon();
  return (
    <section className="section">
      <PageTitle eyebrow="REMI ACADEMY" title={t("courses")}>
        <p>{t("academyText")}</p>
      </PageTitle>
      <div className="service-grid">
        {catalog?.courses.map((c) => (
          <article className="service-card" key={c.id}>
            {c.image_url && (
              <img src={c.image_url} alt={text(c.title)} loading="lazy" />
            )}
            <span className="eyebrow">REMI ACADEMY</span>
            <h2>{text(c.title)}</h2>
            <p>{text(c.description)}</p>
            <Link className="text-link" to={`/${locale}/courses/${c.id}`}>
              {t("discover")}
              <ArrowUpRight size={18} />
            </Link>
          </article>
        ))}
      </div>
      {!catalog?.courses.length && <p className="empty">{t("courseEmpty")}</p>}
    </section>
  );
}
function CourseDetail() {
  const { id } = useParams();
  const { t, catalog, text, locale } = useSalon();
  const course = catalog?.courses.find((c) => c.id === Number(id));
  const [offering, setOffering] = useState("");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const key = useRef(crypto.randomUUID());
  const navigate = useNavigate();
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const data = Object.fromEntries(new FormData(e.currentTarget));
    try {
      const result = await mutation(
        "/registrations",
        {
          ...data,
          offering_id: Number(offering),
          locale,
          policy_accepted: true,
        },
        "POST",
        key.current,
      );
      navigate(`/${locale}/confirmation`, {
        state: { ...result, type: "registration" },
      });
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="section narrow">
      <PageTitle title={course ? text(course.title) : t("courses")} />
      <p>{text(course?.description)}</p>
      <p className="prose">{text(course?.syllabus)}</p>
      {course?.offerings.map((o: any) => (
        <button
          className={
            "offering " + (offering === String(o.id) ? "selected" : "")
          }
          key={o.id}
          disabled={o.remaining < 1 || o.registration_open === false}
          onClick={() => {
            setOffering(String(o.id));
            key.current = crypto.randomUUID();
          }}
        >
          <span>
            {o.sessions
              .map((s: any) =>
                new Date(s.starts_at).toLocaleString(locale, {
                  timeZone: catalog?.settings.timezone,
                }),
              )
              .join(" · ")}
          </span>
          <Money amount={o.price_minor} />
          <span>
            {o.registration_open === false
              ? t("registrationClosed")
              : o.remaining > 0
                ? `${o.remaining} ${t("remaining")}`
                : t("full")}
          </span>
        </button>
      ))}
      {offering && (
        <form className="form-card" onSubmit={submit}>
          <CustomerFields />
          <Field label={t("agree")}>
            <input type="checkbox" required />
          </Field>
          <ErrorMessage error={error} />
          <button className="button" disabled={busy}>
            {busy ? t("loading") : t("register")}
          </button>
        </form>
      )}
    </section>
  );
}
function CustomerFields({ initial = {} }: { initial?: Record<string, any> }) {
  const { t } = useSalon();
  return (
    <>
      <Field label={t("name")}>
        <input
          name="name"
          autoComplete="name"
          required
          maxLength={120}
          defaultValue={initial.name || ""}
        />
      </Field>
      <Field label={t("emailOptional")}>
        <input
          name="email"
          type="email"
          autoComplete="email"
          maxLength={254}
          defaultValue={initial.email || ""}
          dir="ltr"
        />
      </Field>
      <Field label={t("phoneRequired")}>
        <input
          name="phone"
          type="tel"
          required
          autoComplete="tel"
          maxLength={30}
          defaultValue={initial.phone || ""}
          dir="ltr"
        />
      </Field>
      <Field label={t("notes")}>
        <textarea
          name="notes"
          maxLength={1000}
          defaultValue={initial.notes || ""}
        />
      </Field>
    </>
  );
}
function Booking({
  existing,
  onComplete,
}: {
  existing?: any;
  onComplete?: (value: any) => void;
}) {
  const { t, text, catalog, locale } = useSalon();
  const catalogQuery = useCatalog();
  const navigate = useNavigate();
  const [service, setService] = useState(
    String(
      existing?.service_id ||
        new URLSearchParams(location.search).get("service") ||
        "",
    ),
  );
  const staff = "";
  const [date, setDate] = useState("");
  const [slot, setSlot] = useState("");
  const [step, setStep] = useState(0);
  const [customer, setCustomer] = useState<Record<string, any>>({});
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const key = useRef(crypto.randomUUID());
  const times = useQuery({
    queryKey: ["availability", service, staff, date],
    queryFn: () =>
      api<any>(
        `/availability?service_id=${service}&date=${date}${staff ? "&staff_id=" + staff : ""}`,
      ),
    enabled: Boolean(
      service &&
      date &&
      catalog?.settings.booking_enabled &&
      catalog.settings.booking_ready !== false,
    ),
    refetchInterval: 20000,
  });
  const selected = catalog?.services.find((s) => s.id === Number(service));
  useEffect(() => {
    setSlot("");
    key.current = crypto.randomUUID();
  }, [service, staff, date]);
  async function submit() {
    setBusy(true);
    setError(null);
    try {
      const payload = {
        ...customer,
        service_id: Number(service),
        staff_id: staff ? Number(staff) : null,
        starts_at: slot,
        locale,
        policy_accepted: true,
      };
      const result = await mutation(
        existing
          ? `/guest/appointments/${existing.reference}/reschedule`
          : "/appointments",
        payload,
        "POST",
        key.current,
      );
      client.invalidateQueries({ queryKey: ["availability"] });
      client.invalidateQueries({ queryKey: ["admin"] });
      notifySalonChange();
      if (onComplete) onComplete(result);
      else navigate(`/${locale}/confirmation`, { state: result });
    } catch (err) {
      setError(err);
      if (err instanceof ApiError && err.status === 409) {
        setStep(1);
        setSlot("");
        key.current = crypto.randomUUID();
        times.refetch();
      }
    } finally {
      setBusy(false);
    }
  }
  if (!catalog)
    return (
      <section className="section narrow">
        <PageTitle title={t("book")} />
        <ErrorMessage error={catalogQuery.error} />
        {catalogQuery.isPending && (
          <p className="empty" role="status">
            {t("loading")}
          </p>
        )}
      </section>
    );
  if (
    !catalog.settings.booking_enabled ||
    catalog.settings.booking_ready === false
  )
    return (
      <section className="section narrow">
        <PageTitle title={t("book")} />
        <p className="empty">
          {t(
            catalog.settings.booking_enabled
              ? "bookingUnavailable"
              : "bookingClosed",
          )}
        </p>
      </section>
    );
  return (
    <section className="section booking">
      <PageTitle title={existing ? t("reschedule") : t("book")} />
      <ol className="stepper">
        {(["chooseService", "chooseTime", "details", "review"] as const).map(
          (s, i) => (
            <li key={s} className={i <= step ? "active" : ""}>
              <span>{i < step ? <Check size={15} /> : i + 1}</span>
              {t(s)}
            </li>
          ),
        )}
      </ol>
      <div className="booking-layout">
        <div className="form-card">
          <ErrorMessage error={error} />
          {step === 0 && (
            <>
              <h2>{t("chooseService")}</h2>
              <Field label={t("services")}>
                <select
                  value={service}
                  required
                  onChange={(e) => setService(e.target.value)}
                  disabled={Boolean(existing)}
                >
                  <option value="">—</option>
                  {catalog.services
                    .filter((s) => s.bookable)
                    .map((s) => (
                      <option value={s.id} key={s.id}>
                        {text(s.name)}
                      </option>
                    ))}
                </select>
              </Field>
              {!catalog.services.some((s) => s.bookable) && (
                <p>{t("noSlots")}</p>
              )}
              <button
                className="button"
                disabled={!selected?.bookable}
                onClick={() => setStep(1)}
              >
                {t("next")}
                <ArrowRight size={16} />
              </button>
            </>
          )}
          {step === 1 && (
            <>
              <h2>{t("chooseTime")}</h2>
              <Field label={t("date")}>
                <input
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  required
                />
              </Field>
              <ErrorMessage error={times.error} />
              <div className="slots" aria-label={t("chooseTime")}>
                {times.isFetching && <span role="status">{t("loading")}</span>}
                {times.data?.slots.map((s: any) => (
                  <button
                    type="button"
                    key={s.starts_at}
                    disabled={!s.available}
                    aria-pressed={slot === s.starts_at}
                    className={
                      "slot " + (slot === s.starts_at ? "selected" : "")
                    }
                    onClick={() => {
                      setSlot(s.starts_at);
                      key.current = crypto.randomUUID();
                    }}
                  >
                    <span>
                      {new Date(s.starts_at).toLocaleTimeString(locale, {
                        hour: "2-digit",
                        minute: "2-digit",
                        timeZone: catalog.settings.timezone,
                        timeZoneName: "short",
                      })}
                    </span>
                    <small>
                      {t(
                        s.available
                          ? "available"
                          : s.reason === "booked"
                            ? "booked"
                            : "blocked",
                      )}
                    </small>
                  </button>
                ))}
              </div>
              {date &&
                times.isSuccess &&
                !times.isFetching &&
                !times.data?.slots.some((s: any) => s.available) && (
                  <p>{t("noSlots")}</p>
                )}
              <button
                className="button"
                disabled={
                  !slot ||
                  !times.data?.slots.find((s: any) => s.starts_at === slot)
                    ?.available
                }
                onClick={() => setStep(existing ? 3 : 2)}
              >
                {t("next")}
              </button>
            </>
          )}
          {step === 2 && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                setCustomer(Object.fromEntries(new FormData(e.currentTarget)));
                setStep(3);
              }}
            >
              <h2>{t("details")}</h2>
              <CustomerFields initial={customer} />
              <label className="check-field">
                <input type="checkbox" name="marketing_consent" value="1" />
                {t("marketing")}
              </label>
              <button className="button">{t("next")}</button>
            </form>
          )}
          {step === 3 && (
            <>
              <h2>{t("review")}</h2>
              <p>{customer.name || existing?.customer?.name}</p>
              <p>
                <bdi dir="ltr">
                  {customer.phone || existing?.customer?.phone}
                </bdi>
              </p>
              {(customer.email || existing?.customer?.email) && (
                <p dir="ltr">{customer.email || existing?.customer?.email}</p>
              )}
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  submit();
                }}
              >
                <label className="check-field">
                  <input type="checkbox" required />
                  <span>
                    {t("agree")}{" "}
                    <Link to={`/${locale}/policy`} target="_blank">
                      {t("policy")}
                    </Link>
                  </span>
                </label>
                <button className="button" disabled={busy}>
                  {busy
                    ? t("loading")
                    : existing
                      ? t("reschedule")
                      : t("confirm")}
                </button>
              </form>
            </>
          )}
          {step > 0 && (
            <button
              className="text-link back"
              onClick={() => setStep(step - 1)}
            >
              {t("back")}
            </button>
          )}
        </div>
        <aside className="booking-summary">
          <Flower2 size={40} strokeWidth={0.8} />
          <span className="eyebrow">
            {locale === "ar" ? "لحظتكِ مع ريمي" : "YOUR REMI MOMENT"}
          </span>
          <h2>{selected ? text(selected.name) : t("chooseService")}</h2>
          {selected && (
            <>
              <p>
                {selected.duration_minutes} {t("minutes")}
              </p>
              <p>
                <Money amount={selected.price_minor} />
              </p>
            </>
          )}
          {slot && (
            <p>
              {new Date(slot).toLocaleString(locale, {
                timeZone: catalog.settings.timezone,
              })}
              <small>{catalog.settings.timezone}</small>
            </p>
          )}
          <hr />
          <p className="muted">{t("notice")}</p>
        </aside>
      </div>
    </section>
  );
}
function Confirmation() {
  const { t, locale, text, catalog } = useSalon();
  const { state } = useLocation();
  if (!state) return <Navigate to={`/${locale}/manage`} replace />;
  const item = state.data;
  return (
    <section className="section narrow confirmation">
      <span className="success-circle">
        <Check size={28} />
      </span>
      <PageTitle
        title={t(
          state.type === "registration"
            ? "registrationConfirmed"
            : item.status === "pending"
              ? "pendingSaved"
              : "confirmed",
        )}
      />
      <p>{t("reference")}</p>
      <strong className="reference" dir="ltr">
        {item.reference}
      </strong>
      <h2>{text(item.service_name || item.course_title)}</h2>
      {item.starts_at && (
        <p>
          {new Date(item.starts_at).toLocaleString(locale, {
            timeZone: catalog?.settings.timezone,
          })}
        </p>
      )}
      <p>{displayMoney(item.price_minor, item.currency, locale)}</p>
      <p>
        {t(item.customer?.email ? "confirmationNote" : "phoneConfirmationNote")}
      </p>
      {state.management_token && (
        <Link
          className="button"
          to={`/${locale}/manage#token=${encodeURIComponent(state.management_token)}`}
        >
          {t("manage")}
        </Link>
      )}
    </section>
  );
}
function Manage() {
  const { t, locale, catalog } = useSalon();
  const [record, setRecord] = useState<any>();
  const [error, setError] = useState<unknown>();
  const [rescheduling, setRescheduling] = useState(false);
  const [message, setMessage] = useState("");
  const started = useRef(false);
  useEffect(() => {
    if (started.current) return;
    started.current = true;
    const token = new URLSearchParams(location.hash.slice(1)).get("token");
    if (token) {
      history.replaceState(null, "", location.pathname);
      csrf()
        .then(() => mutation("/guest/exchange", { token }))
        .then(setRecord)
        .catch(setError);
    }
  }, []);
  async function recover(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    try {
      await csrf();
      await mutation("/guest/recover", {
        ...Object.fromEntries(new FormData(e.currentTarget)),
        locale,
      });
      setMessage(t("recoverySent"));
    } catch (err) {
      setError(err);
    }
  }
  if (rescheduling)
    return (
      <Booking
        existing={record.data}
        onComplete={(r) => {
          setRecord({ ...r, type: "appointment" });
          setRescheduling(false);
        }}
      />
    );
  return (
    <section className="section narrow">
      <PageTitle title={t("manage")} />
      <ErrorMessage error={error} />
      {message && <p role="status">{message}</p>}
      {record ? (
        <div className="form-card">
          <p dir="ltr">{record.data.reference}</p>
          <p>{statusLabel(record.data.status, locale)}</p>
          {record.data.starts_at && (
            <p>
              {new Date(record.data.starts_at).toLocaleString(locale, {
                timeZone: catalog?.settings.timezone,
              })}
            </p>
          )}
          <button
            className="button"
            disabled={!["pending", "confirmed"].includes(record.data.status)}
            onClick={async () => {
              try {
                const r = await mutation(
                  `/guest/${record.type === "registration" ? "registrations" : "appointments"}/${record.data.reference}/cancel`,
                  {},
                );
                setRecord({ ...record, data: r.data });
              } catch (e) {
                setError(e);
              }
            }}
          >
            {t("cancel")}
          </button>
          {record.type !== "registration" && (
            <button
              className="button secondary"
              disabled={record.data.status !== "confirmed"}
              onClick={() => setRescheduling(true)}
            >
              {t("reschedule")}
            </button>
          )}
        </div>
      ) : (
        <form className="form-card" onSubmit={recover}>
          <h2>{t("recover")}</h2>
          <Field label={t("reference")}>
            <input name="reference" required dir="ltr" />
          </Field>
          <Field label={t("email")}>
            <input name="email" type="email" required dir="ltr" />
          </Field>
          <button className="button">{t("send")}</button>
        </form>
      )}
    </section>
  );
}
function Contact() {
  const { t, catalog, locale } = useSalon();
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<unknown>();
  const settings = catalog?.settings;
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.currentTarget));
    try {
      await mutation("/contact", { ...data, locale });
      setSent(true);
    } catch (err) {
      setError(err);
    }
  }
  return (
    <section className="section">
      <PageTitle title={t("contact")} />
      <div className="booking-layout">
        <div>
          <h2>REMI Nail Spa</h2>
          {settings?.address ? (
            <p>{settings.address}</p>
          ) : (
            <p>{t("contactEmpty")}</p>
          )}
          {settings?.phone && (
            <p>
              <a href={"tel:" + settings.phone} dir="ltr">
                {settings.phone}
              </a>
            </p>
          )}
          {settings?.email && (
            <p>
              <a href={"mailto:" + settings.email}>{settings.email}</a>
            </p>
          )}
          <WorkingHours />
          {settings?.directions_url && (
            <a
              className="text-link"
              href={settings.directions_url}
              rel="noreferrer"
              target="_blank"
            >
              {t("contact")}
              <ArrowUpRight size={18} />
            </a>
          )}
        </div>
        <form className="form-card" onSubmit={submit}>
          {sent ? (
            <p role="status">{t("messageSent")}</p>
          ) : (
            <>
              <Field label={t("name")}>
                <input
                  name="name"
                  autoComplete="name"
                  required
                  maxLength={120}
                />
              </Field>
              <Field label={t("email")}>
                <input
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  maxLength={254}
                />
              </Field>
              <Field label={t("message")}>
                <textarea name="message" required maxLength={5000} />
              </Field>
              <ErrorMessage error={error} />
              <button className="button">
                {t("send")}
                <ArrowUpRight size={18} />
              </button>
            </>
          )}
        </form>
      </div>
    </section>
  );
}
function Policy({ type }: { type: "policy" | "privacy" }) {
  const { t, text, catalog } = useSalon();
  return (
    <section className="section narrow">
      <PageTitle title={t(type)} />
      <div className="prose">{text(catalog?.content[type]) || t("draft")}</div>
    </section>
  );
}

function AdminAlias() {
  const current = useLocation();
  return (
    <Navigate
      replace
      to={"/en" + current.pathname + current.search + current.hash}
    />
  );
}
const Admin = lazy(() => import("./Admin"));
export default function App() {
  return (
    <QueryClientProvider client={client}>
      <BrowserRouter>
        <Routes>
          <Route path="/admin/*" element={<AdminAlias />} />
          <Route path="/:locale/*" element={<Layout />} />
          <Route path="*" element={<Navigate to="/en" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
