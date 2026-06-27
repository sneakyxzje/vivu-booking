import React from "react";

interface Props {
  labelClass: string;
  fieldClass: string;
  title: string;
  description: string;
  price: string;
  discountPrice: string;
  numberOfDays: string;
  numberOfNights: string;
  startLocation: string;
  endLocation: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
}

export const TourFormBasicSection: React.FC<Props> = ({
  labelClass,
  fieldClass,
  title,
  description,
  price,
  discountPrice,
  numberOfDays,
  numberOfNights,
  startLocation,
  endLocation,
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
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label className={labelClass}>
          Giá gốc (VND) <span className="text-red-500">*</span>
        </label>
        <input
          name="price"
          type="number"
          min={0}
          required
          value={price}
          onChange={onChange}
          placeholder="3500000"
          className={fieldClass}
        />
      </div>
      <div>
        <label className={labelClass}>Giá giảm (VND)</label>
        <input
          name="discount_price"
          type="number"
          min={0}
          value={discountPrice}
          onChange={onChange}
          placeholder="3200000"
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
  </>
);
