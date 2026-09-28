import { Steps } from "antd";

export interface BuocForm { ten: string; moTa: string; }
interface Props {
  buocs: BuocForm[];
  hienTai: number;
  loiTheoBuoc: string[][];
  daGhe: number[];
  onChon: (step: number) => void;
}
export function TourFormStepper({ buocs, hienTai, loiTheoBuoc, daGhe, onChon }: Props) {
  return <Steps current={hienTai} onChange={onChon} responsive items={buocs.map((step, index) => {
    const errors = loiTheoBuoc[index] ?? [];
    const invalid = errors.length > 0 && daGhe.includes(index);
    return { title: step.ten, description: invalid ? errors[0] : step.moTa,
      status: invalid ? "error" : index === hienTai ? "process" : errors.length === 0 ? "finish" : "wait" };
  })} />;
}
export default TourFormStepper;
