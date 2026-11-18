import instagram from "./assets/social/instagram.svg?raw";
import facebook from "./assets/social/facebook.svg?raw";
import whatsapp from "./assets/social/whatsapp.svg?raw";

const icons = { instagram, facebook, whatsapp };

/** Static, locally bundled Bootstrap Icons SVGs; no user-provided markup. */
export default function SocialIcon({ name }: { name: keyof typeof icons }) {
  return <span className="footer-brand-icon" aria-hidden="true" dangerouslySetInnerHTML={{ __html: icons[name] }} />;
}
