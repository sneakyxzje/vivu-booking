import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
} from "antd";
import React from "react";
import { Banknote, MapPin, Tag } from "lucide-react";

/**
 * Bước 1: tour này là gì, đi mấy ngày, bán bao nhiêu.
 *
 * Ba nhóm tách rời nhau thay vì một cột mười ô nhập nối đuôi. Người điền một biểu mẫu dài không
 * đọc từng nhãn — họ quét tìm phần mình đang cần, nên phần phải có ranh giới để mà quét.
 */

interface Props {
  labelClass: string;
  fieldClass: string;
  title: string;
  description: string;
  adultPrice: string;
  childPrice: string;
  infantPrice: string;
  numberOfDays: string;
  numberOfNights: string;
  startLocation: string;
  endLocation: string;
  vehicleInfo: string;
  pickupLocation: string;
  onChange: (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => void;
  /** Đặt thẳng một trường, cho các nút gợi ý không đi qua sự kiện của ô nhập. */
  onSet: (name: string, value: string) => void;
}

const dinhDangTien = (giaTri: string) => {
  const so = Number(giaTri);
  if (!so || Number.isNaN(so)) return null;

  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(so);
};

const Nhom: React.FC<{
  tieuDe: string;
  moTa: string;
  icon: React.ReactNode;
  children: React.ReactNode;
}> = ({ tieuDe, moTa, icon, children }) => (
  <UICard  ><UIFlex vertical gap="middle"><div className="mb-4 flex items-start gap-3 border-b border-gray-100 pb-3">
      <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
        {icon}
      </span>
      <div>
        <h3 className="text-sm font-bold text-gray-950">{tieuDe}</h3>
        <p className="mt-0.5 text-xs text-gray-500">{moTa}</p>
      </div>
    </div>{children}</UIFlex></UICard>
);

/** Ô nhập tiền, kèm số đã định dạng ngay dưới để bắt lỗi thừa/thiếu một chữ số. */
const OTien: React.FC<{
  nhan: string;
  ten: string;
  giaTri: string;
  goiY: string;
  batBuoc?: boolean;
  labelClass: string;
  fieldClass: string;
  onChange: Props["onChange"];
}> = ({
  nhan,
  ten,
  giaTri,
  goiY,
  batBuoc,
  labelClass,
  onChange,
}) => (
  <div>
    <label className={labelClass}>
      {nhan} {batBuoc && <span className="text-red-500">*</span>}
    </label>
    <div className="relative">
      <AntInput name={ten} type="number" min={0} step={1000} required={batBuoc} value={giaTri} onChange={onChange} placeholder={goiY} style={{ width: "100%" }} />
      <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-gray-400">
        đ
      </span>
    </div>
    <p className="mt-1 h-4 text-[11px] font-semibold text-primary-600">
      {dinhDangTien(giaTri) ?? ""}
    </p>
  </div>
);

export const TourFormBasicSection: React.FC<Props> = ({
  labelClass,
  fieldClass,
  title,
  description,
  adultPrice,
  childPrice,
  infantPrice,
  numberOfDays,
  numberOfNights,
  startLocation,
  endLocation,
  vehicleInfo,
  pickupLocation,
  onChange,
  onSet,
}) => {
  const soNgay = Number(numberOfDays);
  const soDem = Number(numberOfNights);
  const demSai =
    Number.isFinite(soNgay) && Number.isFinite(soDem) && soDem > soNgay;

  return (
    <UIFlex vertical gap={20} ><Nhom
        tieuDe="Thông tin"
        moTa="Tên và phần mô tả"
        icon={<Tag className="h-4 w-4" />}
      >
        <UIFlex vertical gap={16} ><div>
            <label className={labelClass}>
              Tiêu đề tour <span className="text-red-500">*</span>
            </label>
            <AntInput name="title" required maxLength={255} value={title} onChange={onChange} placeholder="VD: Hà Nội - Hạ Long 2N1Đ, du thuyền 5 sao" style={{ width: "100%" }} />
            <p className="mt-1 text-[11px] text-gray-400">
              Nêu điểm đến và thời lượng ngay trong tên — đó là hai thứ khách
              lọc trước tiên.
            </p>
          </div><div>
            <label className={labelClass}>Mô tả</label>
            <AntInput.TextArea name="description" rows={5} value={description} onChange={onChange} placeholder="Điểm nổi bật, trải nghiệm chính, tour hợp với ai..." style={{ width: "100%" }} />
          </div></UIFlex>
      </Nhom><Nhom
        tieuDe="Thời lượng & hành trình"
        moTa="Đi mấy ngày, xuất phát từ đâu, về đâu."
        icon={<MapPin className="h-4 w-4" />}
      >
        <UIFlex vertical gap={16} ><div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelClass}>
                Số ngày <span className="text-red-500">*</span>
              </label>
              <AntInput name="number_of_days" type="number" min={1} required value={numberOfDays} onChange={onChange} style={{ width: "100%" }} />
            </div>
            <div>
              <label className={labelClass}>
                Số đêm <span className="text-red-500">*</span>
              </label>
              <AntInput name="number_of_nights" type="number" min={0} required value={numberOfNights} onChange={onChange} style={{ width: "100%" }} />
              {demSai ? (
                <p className="mt-1 text-[11px] font-semibold text-red-600">
                  Số đêm không được lớn hơn số ngày.
                </p>
              ) : (
                soNgay > 1 &&
                soDem !== soNgay - 1 && (
                  <AntButton htmlType="button" onClick={() =>
                      onSet("number_of_nights", String(soNgay - 1))
                    }>Dùng {soNgay}N{soNgay - 1}Đ như thường lệ
                  </AntButton>
                )
              )}
            </div>
          </div><div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelClass}>
                Điểm khởi hành <span className="text-red-500">*</span>
              </label>
              <AntInput name="start_location" required value={startLocation} onChange={onChange} placeholder="Hà Nội" style={{ width: "100%" }} />
            </div>
            <div>
              <label className={labelClass}>Điểm kết thúc</label>
              <AntInput name="end_location" value={endLocation} onChange={onChange} placeholder="Hạ Long" style={{ width: "100%" }} />
            </div>
          </div><div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelClass}>Phương tiện di chuyển</label>
              <AntInput name="vehicle_info" value={vehicleInfo} onChange={onChange} placeholder="VD: Xe giường nằm 34 chỗ đời mới, có wifi" style={{ width: "100%" }} />
            </div>
            <div>
              <label className={labelClass}>Điểm đón khách</label>
              <AntInput name="pickup_location" value={pickupLocation} onChange={onChange} placeholder="VD: Nhà hát Lớn Hà Nội - 1 Tràng Tiền, có mặt trước 30 phút" style={{ width: "100%" }} />
            </div>
          </div></UIFlex>
      </Nhom><Nhom
        tieuDe="Giá vé"
        moTa="Giá một khách. Ba mức tuổi đều bắt buộc — để 0 nghĩa là miễn phí."
        icon={<Banknote className="h-4 w-4" />}
      >
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <OTien
            nhan="Người lớn (12+ tuổi)"
            ten="adult_price"
            giaTri={adultPrice}
            goiY="4000000"
            batBuoc
            labelClass={labelClass}
            fieldClass={fieldClass}
            onChange={onChange}
          />
          <OTien
            nhan="Trẻ em (2-12 tuổi)"
            ten="child_price"
            giaTri={childPrice}
            goiY="2800000"
            batBuoc
            labelClass={labelClass}
            fieldClass={fieldClass}
            onChange={onChange}
          />
          <OTien
            nhan="Em bé (dưới 2 tuổi)"
            ten="infant_price"
            giaTri={infantPrice}
            goiY="0"
            batBuoc
            labelClass={labelClass}
            fieldClass={fieldClass}
            onChange={onChange}
          />
        </div>

        {Number(adultPrice) > 0 && !childPrice && (
          <AntButton htmlType="button" onClick={() =>
              onSet("child_price", String(Math.round(Number(adultPrice) * 0.7)))
            }>Điền giá trẻ em bằng 70% giá người lớn
          </AntButton>
        )}
      </Nhom></UIFlex>
  );
};
