import { useState } from "react";
import type { FormEvent } from "react";
import { Field, ErrorMessage, useSalon } from "./App";
import { mutation } from "./api";
export default function OwnerAccount({ onChanged }: { onChanged: () => void }) {
  const { t } = useSalon();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await mutation(
        "/auth/change-password",
        Object.fromEntries(new FormData(event.currentTarget)),
      );
      onChanged();
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  return (
    <form className="form-card owner-account" onSubmit={submit}>
      <h2>{t("changePassword")}</h2>
      <Field label={t("currentPassword")}>
        <input
          name="current_password"
          type="password"
          autoComplete="current-password"
          required
        />
      </Field>
      <Field label={t("newPassword")}>
        <input
          name="password"
          type="password"
          autoComplete="new-password"
          required
          minLength={12}
        />
      </Field>
      <Field label={t("passwordConfirmation")}>
        <input
          name="password_confirmation"
          type="password"
          autoComplete="new-password"
          required
          minLength={12}
        />
      </Field>
      <ErrorMessage error={error} />
      <button className="button" disabled={busy}>
        {busy ? t("loading") : t("changePassword")}
      </button>
    </form>
  );
}
