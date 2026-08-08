import React from "react";

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
  onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
}

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
}) => (
  <>
    <div>
      <label className={labelClass}>
        Tiêu đề tour <span className="text-red-500">*</span>
      </label>
      <input
        name="title"
        required
        value={title}
        onChange={onChange}
        placeholder="VD: Tour Hạ Long 2N1Đ"
        className={fieldClass}
      />
    </div>
    <div>
      <label className={labelClass}>Mô tả</label>
      <textarea
        name="description"
        rows={5}
        value={description}
        onChange={onChange}
        placeholder="Điểm nổi bật, trải nghiệm chính, lịch trình tổng quan..."
        className={`${fieldClass} resize-y`}
      />
    </div>
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div>
        <label className={labelClass}>
          Giá người lớn (12+ tuổi) <span className="text-red-500">*</span>
        </label>
        <input
          name="adult_price"
          type="number"
          min={0}
          required
          value={adultPrice}
          onChange={onChange}
          placeholder="4000000"
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>
          Giá trẻ em (2-12 tuổi) <span className="text-red-500">*</span>
        </label>
        <input
          name="child_price"
          type="number"
          min={0}
          required
          value={childPrice}
          onChange={onChange}
          placeholder="2800000"
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>
          Giá em bé (&lt; 2 tuổi) <span className="text-red-500">*</span>
        </label>
        <input
          name="infant_price"
          type="number"
          min={0}
          required
          value={infantPrice}
          onChange={onChange}
          placeholder="0"
          className={fieldClass}
        />
      </div>
    </div>
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label className={labelClass}>
          Số ngày <span className="text-red-500">*</span>
        </label>
        <input
          name="number_of_days"
          type="number"
          min={1}
          required
          value={numberOfDays}
          onChange={onChange}
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>
          Số đêm <span className="text-red-500">*</span>
        </label>
        <input
          name="number_of_nights"
          type="number"
          min={0}
          required
          value={numberOfNights}
          onChange={onChange}
          className={fieldClass}
        />
      </div>
    </div>
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label className={labelClass}>
          Điểm khởi hành <span className="text-red-500">*</span>
        </label>
        <input
          name="start_location"
          required
          value={startLocation}
          onChange={onChange}
          placeholder="Hà Nội"
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>Điểm kết thúc</label>
        <input
          name="end_location"
          value={endLocation}
          onChange={onChange}
          placeholder="Hạ Long"
          className={fieldClass}
        />
      </div>
    </div>
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label className={labelClass}>Phương tiện di chuyển</label>
        <input
          name="vehicle_info"
          value={vehicleInfo}
          onChange={onChange}
          placeholder="VD: Xe giường nằm 34 chỗ đời mới, có wifi"
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>Điểm đón khách</label>
        <input
          name="pickup_location"
          value={pickupLocation}
          onChange={onChange}
          placeholder="VD: Nhà hát Lớn Hà Nội - 1 Tràng Tiền, đến trước 30 phút"
          className={fieldClass}
        />
      </div>
    </div>
  </>
);
