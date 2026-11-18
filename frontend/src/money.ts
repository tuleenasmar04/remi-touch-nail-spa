/** Convert a decimal price to integer minor units without floating-point rounding. */
export function moneyToMinor(value: string, digits: number): number | null {
  if (!value.trim()) return null;
  if (!new RegExp(`^\\d+(?:\\.\\d{0,${digits}})?$`).test(value))
    throw Error("Invalid price precision");
  const [whole, fraction = ""] = value.split(".");
  const result =
    BigInt(whole) * 10n ** BigInt(digits) +
    BigInt(fraction.padEnd(digits, "0") || "0");
  if (result > 999999999n) throw Error("Price exceeds supported range");
  return Number(result);
}
