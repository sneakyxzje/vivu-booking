import { useEffect, useState } from "react";
import { Button, Form, Input } from "antd";
import api from "@/services/api";
import { validateEmail } from "@/utils/validation";

interface Props {
  publicToken: string;
  onVerified: (token: string, expiresIn: number) => Promise<void>;
}

export default function PassengerEmailVerification({ publicToken, onVerified }: Props) {
  const [email, setEmail] = useState("");
  const [otp, setOtp] = useState("");
  const [challenge, setChallenge] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [retryAt, setRetryAt] = useState(0);
  const [expiresAt, setExpiresAt] = useState(0);
  const [now, setNow] = useState(Date.now);

  useEffect(() => {
    if (!retryAt && !expiresAt) return;
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, [retryAt, expiresAt]);

  const remaining = Math.max(0, Math.ceil((retryAt - now) / 1000));
  const expired = Boolean(challenge) && now >= expiresAt;
  const showError = (err: unknown) => setError(
    (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      || "Không thể xác thực lúc này. Vui lòng thử lại.",
  );

  const send = async () => {
    if (busy || remaining || !validateEmail(email.trim())) return;
    setBusy(true);
    setError("");
    setChallenge("");
    setOtp("");
    try {
      const res = await api.post(`/bookings/${publicToken}/passengers/send-otp`, { email: email.trim() });
      const result = res.data.data;
      const sentAt = Date.now();
      setNow(sentAt);
      setChallenge(result.challenge_id);
      setRetryAt(sentAt + result.retry_after * 1000);
      setExpiresAt(sentAt + result.expires_in * 1000);
    } catch (err) {
      showError(err);
    } finally {
      setBusy(false);
    }
  };

  const verify = async () => {
    if (busy || expired || !/^\d{6}$/.test(otp)) return;
    setBusy(true);
    setError("");
    try {
      const res = await api.post(`/bookings/${publicToken}/passengers/verify-otp`, { challenge_id: challenge, otp });
      await onVerified(res.data.data.access_token, res.data.data.expires_in);
    } catch (err) {
      showError(err);
    } finally {
      setBusy(false);
    }
  };

  return <section className="border-t border-slate-200 pt-5" aria-label="Xác thực email">
    <h2 className="mb-3 text-sm font-semibold text-slate-900">Xác thực email</h2>
    <Form layout="vertical" onFinish={challenge ? verify : send}>
      <Form.Item style={{ marginBottom: 12 }}>
        <Input aria-label="Email đã dùng khi đặt tour" type="email" autoComplete="email" placeholder="Email đã đặt tour" value={email} disabled={busy || Boolean(challenge)} onChange={event => setEmail(event.target.value)} />
      </Form.Item>
      {challenge ? <>
        <p className="mb-3 text-xs text-slate-500" role="status">Đã gửi mã đến email đặt tour. Mã có hiệu lực 5 phút.</p>
        <Form.Item style={{ marginBottom: 12 }}>
          <Input aria-label="Mã OTP 6 số" autoComplete="one-time-code" inputMode="numeric" maxLength={6} placeholder="Nhập mã 6 số" value={otp} disabled={busy || expired} onChange={event => setOtp(event.target.value.replace(/\D/g, ""))} />
        </Form.Item>
        {expired && <p className="mb-3 text-xs text-rose-700" role="status">Mã đã hết hạn. Vui lòng gửi lại mã.</p>}
        <Button htmlType="submit" type="primary" block loading={busy} disabled={expired || !/^\d{6}$/.test(otp)}>Xác thực</Button>
        <div className="mt-2 flex items-center justify-between gap-2">
          <Button type="link" size="small" disabled={busy || remaining > 0} onClick={send}>{remaining ? `Gửi lại sau ${remaining}s` : "Gửi lại mã"}</Button>
          <Button type="text" size="small" disabled={busy} onClick={() => { setChallenge(""); setOtp(""); setError(""); }}>Đổi email</Button>
        </div>
      </> : <Button htmlType="submit" block loading={busy} disabled={remaining > 0 || !validateEmail(email.trim())}>{remaining ? `Gửi lại sau ${remaining}s` : "Gửi mã OTP"}</Button>}
      {error && <p role="alert" className="mt-3 text-sm text-rose-700">{error}</p>}
    </Form>
  </section>;
}
