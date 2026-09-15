import type { ReactNode } from "react";
import { AntStepperModal } from "./AntStepperModal";
export interface BuocModal {
  ten: string;
  moTa?: string;
  noiDung: ReactNode;
  chuaXong?: string | null;
}
interface Props {
  isOpen: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  buoc: BuocModal[];
  hienTai: number;
  onDoiBuoc: (index: number) => void;
  nhanHoanTat: string;
  onHoanTat: () => void;
  dangChay?: boolean;
  sacThai?: "chinh" | "nguy-hiem";
  size?: "lg" | "xl" | "2xl";
}
export function StepperModal({ isOpen, sacThai, ...props }: Props) {
  return isOpen ? <AntStepperModal {...props} danger={sacThai === "nguy-hiem"} /> : null;
}
export default StepperModal;
