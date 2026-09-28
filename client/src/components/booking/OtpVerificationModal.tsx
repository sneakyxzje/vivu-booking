import { Alert, Button, Flex, Form, Input, Modal, Typography } from "antd";
import { useEffect, useRef, useState } from "react";
import otpService from "@/services/otpService";

interface OtpVerificationModalProps {
  isOpen: boolean;
  email: string;
  customerName: string;
  tourTitle?: string;
  challenge: string;
  onClose: () => void;
  onVerified: (token: string) => void | Promise<void>;
}

function OtpForm({ email, customerName, tourTitle, challenge, onVerified, onBusyChange }: Omit<OtpVerificationModalProps, "isOpen" | "onClose"> & { onBusyChange: (busy: boolean) => void }) {
  const currentChallenge = useRef(challenge);
  const [digits, setDigits] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);
  const [verified, setVerified] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [expirySeconds, setExpirySeconds] = useState(300);
  const [resendSeconds, setResendSeconds] = useState(60);
  const [generation, setGeneration] = useState(0);
  const expiry = useRef(0);
  const resendAt = useRef(0);
  const mounted = useRef(true);
  useEffect(() => {
    mounted.current = true;
    expiry.current = Date.now() + 300_000;
    resendAt.current = Date.now() + 60_000;
    const timer = window.setInterval(() => {
      setExpirySeconds(Math.max(0, Math.ceil((expiry.current - Date.now()) / 1000)));
      setResendSeconds(Math.max(0, Math.ceil((resendAt.current - Date.now()) / 1000)));
    }, 1000);
    return () => { mounted.current = false; window.clearInterval(timer); };
  }, []);

  const verify = async () => {
    const otp = digits.join("");
    if (loading || verified || resending) return;
    if (!/^\d{6}$/.test(otp)) { setError("Vui lòng nhập đủ 6 chữ số mã OTP."); return; }
    if (Date.now() >= expiry.current) { setError("Mã OTP đã hết hạn. Vui lòng gửi lại mã mới."); return; }
    setLoading(true);
    onBusyChange(true);
    setError("");
    try {
      const result = await otpService.verifyOtp({ email, otp, challenge: currentChallenge.current });
      if (!mounted.current) return;
      setVerified(true);
      setNotice("Xác thực email thành công. Đang xử lý đặt tour…");
      await onVerified(result.data.verification_token);
    } catch (err: unknown) {
      if (mounted.current) setError(err instanceof Error ? err.message : "Mã OTP không đúng. Vui lòng thử lại.");
    } finally {
      if (mounted.current) { setLoading(false); onBusyChange(false); }
    }
  };

  const resend = async () => {
    if (Date.now() < resendAt.current || resending || loading || verified) return;
    setResending(true);
    onBusyChange(true);
    setError("");
    setNotice("");
    try {
      const result = await otpService.sendOtp({ email, customer_name: customerName, tour_title: tourTitle });
      if (!mounted.current) return;
      currentChallenge.current = result.data.challenge;
      setDigits([]);
      setGeneration(value => value + 1);
      expiry.current = Date.now() + 300_000;
      resendAt.current = Date.now() + 60_000;
      setExpirySeconds(300);
      setResendSeconds(60);
      setNotice("Mã OTP mới đã được gửi vào email của bạn.");
    } catch (error) {
      if (mounted.current) setError(error instanceof Error ? error.message : "Chưa gửi được mã OTP. Vui lòng thử lại.");
    } finally {
      if (mounted.current) { setResending(false); onBusyChange(false); }
    }
  };

  return <Form layout="vertical" onFinish={verify}>
    <Typography.Paragraph>Mã gồm 6 chữ số đã được gửi đến <Typography.Text strong>{email}</Typography.Text>.</Typography.Paragraph>
    <Form.Item label="Mã xác thực" validateStatus={error ? "error" : undefined} help={error || undefined}>
      <Input.OTP key={generation} length={6} formatter={value => value.replace(/\D/g, "")} inputMode="numeric"
        disabled={loading || resending || verified || expirySeconds === 0}
        onInput={values => { setDigits(values); setError(""); }} />
    </Form.Item>
    <Flex justify="space-between" align="center" wrap gap="small" style={{ marginBottom: 16 }}>
      <Typography.Text type={expirySeconds < 60 ? "danger" : "secondary"}>
        Hết hạn sau: {String(Math.floor(expirySeconds / 60)).padStart(2, "0")}:{String(expirySeconds % 60).padStart(2, "0")}
      </Typography.Text>
      <Button onClick={resend} loading={resending} disabled={resendSeconds > 0 || loading || verified}>
        {resendSeconds > 0 ? `Gửi lại (${resendSeconds}s)` : "Gửi lại mã"}
      </Button>
    </Flex>
    {notice && <Alert type="success" showIcon title={notice} style={{ marginBottom: 16 }} />}
    {expirySeconds === 0 && !verified && <Alert type="warning" showIcon title="Mã đã hết hạn. Hãy gửi lại mã để tiếp tục." style={{ marginBottom: 16 }} />}
    <Button type="primary" htmlType="submit" block loading={loading} disabled={resending || verified || digits.join("").length !== 6 || expirySeconds === 0}>Xác thực và tiếp tục</Button>
    <Typography.Paragraph type="secondary" style={{ marginTop: 16, marginBottom: 0 }}>Kiểm tra cả thư mục Spam/Rác nếu bạn chưa nhận được email.</Typography.Paragraph>
  </Form>;
}

export default function OtpVerificationModal(props: OtpVerificationModalProps) {
  const [busy, setBusy] = useState(false);
  return <Modal open={props.isOpen} title="Xác thực email đặt tour" footer={null} width={480}
    onCancel={props.onClose} closable={!busy} keyboard={!busy} mask={{ closable: false }} destroyOnHidden>
    {props.isOpen && <OtpForm key={props.challenge} {...props} onVerified={async token => { setBusy(false); await props.onVerified(token); }} onBusyChange={setBusy} />}
  </Modal>;
}
