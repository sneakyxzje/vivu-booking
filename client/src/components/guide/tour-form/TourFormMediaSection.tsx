import React from "react";
import { ImagePlus, Images, Star, Trash2 } from "lucide-react";

/**
 * Bước 4 (phần ảnh): ảnh bìa và bộ ảnh tour.
 *
 * ## Chọn ảnh lần hai là THÊM, không phải THAY
 *
 * Ô chọn nhiều tệp của trình duyệt luôn trả về đúng những tệp vừa chọn trong lần mở hộp thoại
 * ấy. Trước đây màn hình lấy nguyên danh sách đó làm bộ ảnh mới, nên ai chọn năm ảnh rồi mở
 * lại chọn thêm một ảnh nữa sẽ mất sạch năm ảnh đầu — mà không có gì báo.
 */

interface Props {
  labelClass: string;
  thumbnailName: string | null;
  thumbnailPreview: string;
  thumbnailUrl: string;
  imagePreviews: string[];
  onThumbnailChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onThumbnailRemove: () => void;
  onGalleryChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onRemoveGalleryImage: (index: number) => void;
}

export const TourFormMediaSection: React.FC<Props> = ({
  labelClass,
  thumbnailName,
  thumbnailPreview,
  thumbnailUrl,
  imagePreviews,
  onThumbnailChange,
  onThumbnailRemove,
  onGalleryChange,
  onRemoveGalleryImage,
}) => {
  const coAnhBia = Boolean(thumbnailPreview || thumbnailUrl);

  return (
    <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <div className="mb-4 flex items-start gap-3 border-b border-gray-100 pb-3">
        <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
          <Images className="h-4 w-4" />
        </span>
        <div>
          <h3 className="text-sm font-bold text-gray-950">Hình ảnh</h3>
          <p className="mt-0.5 text-xs text-gray-500">
            Ảnh bìa là thứ khách thấy ở danh sách tour. PNG, JPG hoặc WEBP, mỗi ảnh tối đa 5MB.
          </p>
        </div>
      </div>

      <div className="space-y-5">
        <div>
          <label className={labelClass}>
            Ảnh bìa
            <span className="ml-1.5 text-xs font-medium text-gray-400">(nên có)</span>
          </label>

          {coAnhBia ? (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="relative w-full overflow-hidden rounded-xl border border-gray-200 sm:w-64">
                <img
                  src={thumbnailPreview || thumbnailUrl}
                  alt="Ảnh bìa tour"
                  className="aspect-[16/10] w-full object-cover"
                />
                <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-bold text-white">
                  <Star className="h-3 w-3" />
                  Ảnh bìa
                </span>
              </div>

              <div className="flex flex-col gap-2">
                <p className="max-w-xs truncate text-xs text-gray-500">
                  {thumbnailName ?? "Ảnh đang dùng"}
                </p>
                <label className="inline-flex w-fit cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                  <input
                    name="thumbnail_file"
                    type="file"
                    accept="image/*"
                    onChange={onThumbnailChange}
                    className="sr-only"
                  />
                  Đổi ảnh khác
                </label>
                <button
                  type="button"
                  onClick={onThumbnailRemove}
                  className="inline-flex w-fit items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  Bỏ ảnh bìa
                </button>
              </div>
            </div>
          ) : (
            <label className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center transition-colors hover:border-primary-300 hover:bg-primary-50/60">
              <input
                name="thumbnail_file"
                type="file"
                accept="image/*"
                onChange={onThumbnailChange}
                className="sr-only"
              />
              <ImagePlus className="h-7 w-7 text-gray-300" />
              <span className="mt-2 text-sm font-semibold text-gray-800">Chọn ảnh bìa</span>
              <span className="mt-0.5 text-xs text-gray-500">Ảnh ngang, tỉ lệ 16:10 là đẹp nhất</span>
            </label>
          )}
        </div>

        <div>
          <div className="mb-2 flex items-center justify-between gap-3">
            <label className={`${labelClass} mb-0`}>
              Bộ ảnh tour
              {imagePreviews.length > 0 && (
                <span className="ml-1.5 text-xs font-semibold text-primary-600">
                  {imagePreviews.length} ảnh
                </span>
              )}
            </label>
            <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-primary-50 px-2.5 py-1.5 text-[11px] font-semibold text-primary-700 transition-colors hover:bg-primary-100">
              <input
                name="images"
                type="file"
                accept="image/*"
                multiple
                onChange={onGalleryChange}
                className="sr-only"
              />
              <ImagePlus className="h-3.5 w-3.5" />
              Thêm ảnh
            </label>
          </div>

          {imagePreviews.length === 0 ? (
            <label className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center transition-colors hover:border-primary-300 hover:bg-primary-50/60">
              <input
                name="images"
                type="file"
                accept="image/*"
                multiple
                onChange={onGalleryChange}
                className="sr-only"
              />
              <span className="text-sm font-semibold text-gray-800">Chọn nhiều ảnh cùng lúc</span>
              <span className="mt-0.5 text-xs text-gray-500">
                Chọn thêm lần nữa sẽ nối vào bộ ảnh, không xóa ảnh đã chọn
              </span>
            </label>
          ) : (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {imagePreviews.map((preview, index) => (
                <div
                  key={preview}
                  className="group relative overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
                >
                  <img src={preview} alt="" className="aspect-[4/3] w-full object-cover" />
                  <button
                    type="button"
                    onClick={() => onRemoveGalleryImage(index)}
                    className="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm transition-colors hover:bg-red-50 hover:text-red-600"
                    aria-label={`Xóa ảnh ${index + 1}`}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
};
