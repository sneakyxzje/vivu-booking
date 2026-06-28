import React from "react";

interface Props {
  labelClass: string;
  thumbnailName: string | null;
  thumbnailPreview: string;
  thumbnailUrl: string;
  imagePreviews: string[];
  onThumbnailChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
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
  onGalleryChange,
  onRemoveGalleryImage,
}) => (
  <>
    <div>
      <label className={labelClass}>Ảnh thumbnail</label>
      <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center transition hover:border-primary-300 hover:bg-primary-50/60">
        <input
          name="thumbnail_file"
          type="file"
          accept="image/*"
          onChange={onThumbnailChange}
          className="sr-only"
        />
        <span className="mt-3 text-sm font-semibold text-gray-800">
          Chọn ảnh từ máy
        </span>
        <span className="mt-1 text-xs text-gray-500">
          PNG, JPG, WEBP tối đa 5MB
        </span>
        {(thumbnailName || thumbnailPreview || thumbnailUrl) && (
          <span className="mt-3 max-w-full truncate rounded-lg bg-white px-3 py-1 text-xs font-medium text-gray-600">
            {thumbnailName ?? "Ảnh đã chọn"}
          </span>
        )}
      </label>
    </div>

    <div>
      <label className={labelClass}>Bộ ảnh tour</label>
      <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center transition hover:border-primary-300 hover:bg-primary-50/60">
        <input
          name="images"
          type="file"
          accept="image/*"
          multiple
          onChange={onGalleryChange}
          className="sr-only"
        />
        <span className="mt-2 text-sm font-semibold text-gray-800">
          Chọn nhiều ảnh
        </span>
        <span className="mt-1 text-xs text-gray-500">
          Mỗi ảnh tối đa 5MB
        </span>
      </label>

      {imagePreviews.length > 0 && (
        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {imagePreviews.map((preview, index) => (
            <div
              key={preview}
              className="group relative overflow-hidden rounded-lg border border-gray-100 bg-gray-100"
            >
              <img src={preview} alt="" className="aspect-[4/3] w-full object-cover" />
              <button
                type="button"
                onClick={() => onRemoveGalleryImage(index)}
                className="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm transition hover:bg-red-50 hover:text-red-600"
                aria-label="Xóa ảnh"
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  </>
);
