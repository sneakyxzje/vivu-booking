import type { PassengerPayload } from "../services/bookingService";

export type PassengerRow = Required<Pick<PassengerPayload, "name" | "type" | "is_contact">> & {
  gender: string;
  date_of_birth: string;
  id_type: string;
  identity_number: string;
  phone: string;
  special_request: string;
};
export type ImportCell = string | number | boolean | Date | null;
export type ImportTable = { name: string; rows: ImportCell[][] };
export type ImportContext = {
  adult_count: number;
  child_count: number;
  infant_count: number;
  departure_date: string | null;
};
export type ImportPreview = { passengers: PassengerRow[]; errors: string[]; warnings: string[] };

export const passengerTypeLabels = { adult: "Người lớn", child: "Trẻ em", infant: "Em bé" };
export const emptyPassenger = (type: PassengerRow["type"]): PassengerRow => ({
  name: "", type, gender: "", date_of_birth: "", id_type: "cccd",
  identity_number: "", phone: "", special_request: "", is_contact: false,
});

const normalize = (value: string) => value.trim().toLowerCase().normalize("NFD")
  .replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/[^a-z0-9]/g, "");
const text = (value: ImportCell | undefined) => value == null ? "" : String(value).trim();
const columns: Record<keyof PassengerRow, string[]> = {
  name: ["Họ và tên", "Họ tên", "Tên hành khách", "name", "full name"],
  type: ["Loại khách", "Loại hành khách", "type", "passenger type"],
  gender: ["Giới tính", "gender", "sex"],
  date_of_birth: ["Ngày sinh", "date of birth", "dob", "birthday"],
  id_type: ["Loại giấy tờ", "id type"],
  identity_number: ["Số giấy tờ", "Số CCCD", "CCCD", "CCCD/Hộ chiếu", "identity number", "passport number"],
  phone: ["Điện thoại", "Số điện thoại", "SĐT", "phone", "phone number"],
  special_request: ["Yêu cầu riêng", "Yêu cầu đặc biệt", "special request", "Ghi chú"],
  is_contact: ["Người đại diện", "Người liên hệ", "is contact", "contact"],
};
const aliases = new Map(Object.entries(columns).flatMap(([key, names]) =>
  [key, ...names].map(name => [normalize(name), key as keyof PassengerRow] as const)));
const fieldFor = (value: ImportCell) => aliases.get(normalize(text(value)));

export function hasPassengerHeader(rows: ImportCell[][]): boolean {
  return rows.slice(0, 20).some(row => {
    const fields = row.map(fieldFor);
    return fields.includes("name") && fields.includes("type");
  });
}

function dateValue(value: ImportCell | undefined): string | null {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value.toISOString().slice(0, 10);
  const raw = text(value);
  if (!raw) return "";
  let year: number, month: number, day: number;
  let match = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(raw);
  if (match) [, year, month, day] = match.map(Number);
  else {
    match = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(raw);
    if (!match) return null;
    [, day, month, year] = match.map(Number);
  }
  const parsed = new Date(Date.UTC(year, month - 1, day));
  if (year < 1900 || parsed.getUTCFullYear() !== year || parsed.getUTCMonth() !== month - 1 || parsed.getUTCDate() !== day) return null;
  return parsed.toISOString().slice(0, 10);
}

function enumValue(raw: string, choices: Record<string, string[]>): string | null {
  if (!raw) return "";
  const key = normalize(raw);
  return Object.entries(choices).find(([value, names]) => [value, ...names].some(n => normalize(n) === key))?.[0] ?? null;
}

/** Pure normalization: never writes bookings or silently discards a bad passenger. */
export function previewPassengers(table: ImportTable, context: ImportContext, today = new Date()): ImportPreview {
  const result: ImportPreview = { passengers: [], errors: [], warnings: [] };
  const headerIndex = table.rows.slice(0, 20).findIndex(row => {
    const fields = row.map(fieldFor);
    return fields.includes("name") && fields.includes("type");
  });
  if (headerIndex < 0) {
    result.errors.push('Không tìm thấy hai cột bắt buộc “Họ và tên” và “Loại khách”. Hãy dùng file mẫu.');
    return result;
  }
  const headers = table.rows[headerIndex].map(fieldFor);
  const mapped = headers.filter(Boolean);
  if (new Set(mapped).size !== mapped.length) {
    result.errors.push("Có cột thông tin bị lặp. Mỗi thông tin chỉ dùng một cột.");
    return result;
  }
  const unknown = table.rows[headerIndex].filter((cell, index) => text(cell) && !headers[index]).map(text);
  if (unknown.length) result.warnings.push(`Không nhập các cột: ${unknown.join(", ")}.`);
  const nonempty = table.rows.slice(headerIndex + 1).map((cells, i) => ({ cells, line: headerIndex + i + 2 }))
    .filter(({ cells }) => cells.some(cell => text(cell)));
  if (!nonempty.length || nonempty.length > 50) {
    result.errors.push(nonempty.length ? "Chỉ nhập tối đa 50 hành khách mỗi lần." : "File chưa có hành khách.");
    return result;
  }
  const identities = new Map<string, number>();
  const counts = { adult: 0, child: 0, infant: 0 };
  const todayDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
  for (const { cells, line } of nonempty) {
    const read = (field: keyof PassengerRow) => cells[headers.indexOf(field)];
    const fail = (message: string) => result.errors.push(`Dòng ${line}: ${message}`);
    const type = enumValue(text(read("type")), { adult: ["Người lớn", "NL"], child: ["Trẻ em", "TE"], infant: ["Em bé", "EB"] });
    const passenger = emptyPassenger((type || "adult") as PassengerRow["type"]);
    if (!type) fail("Loại khách phải là Người lớn, Trẻ em hoặc Em bé.");
    else counts[passenger.type]++;
    passenger.name = text(read("name"));
    if (!passenger.name || passenger.name.length > 255) fail("Họ tên cần có từ 1 đến 255 ký tự.");
    const gender = enumValue(text(read("gender")), { male: ["Nam"], female: ["Nữ"], other: ["Khác"] });
    if (gender === null) fail("Giới tính phải là Nam, Nữ hoặc Khác.");
    passenger.gender = gender ?? "";
    const dob = dateValue(read("date_of_birth"));
    if (dob === null || (dob && dob > todayDate)) fail("Ngày sinh không hợp lệ. Dùng ngày/tháng/năm hoặc YYYY-MM-DD, không ở tương lai.");
    passenger.date_of_birth = dob ?? "";
    if (dob && type && context.departure_date) {
      const departure = context.departure_date.slice(0, 10);
      const age = Number(departure.slice(0, 4)) - Number(dob.slice(0, 4)) - (departure.slice(5) < dob.slice(5) ? 1 : 0);
      const ageType = age < 2 ? "infant" : age < 12 ? "child" : "adult";
      if (dob > departure || ageType !== type) fail("Ngày sinh không khớp loại khách tại ngày khởi hành.");
    }
    const idType = enumValue(text(read("id_type")), { cccd: ["Căn cước", "Căn cước công dân"], cmnd: ["Chứng minh nhân dân"], passport: ["Hộ chiếu"], birth_certificate: ["Giấy khai sinh"] });
    if (idType === null) fail("Loại giấy tờ không hợp lệ.");
    passenger.id_type = idType || "cccd";
    for (const field of ["identity_number", "phone"] as const) {
      passenger[field] = text(read(field));
      if (typeof read(field) === "number") fail(`${field === "phone" ? "Điện thoại" : "Số giấy tờ"} đang là ô số trong Excel. Đổi thành Text và nhập lại để giữ số 0 ở đầu.`);
    }
    if (passenger.identity_number.length > 50) fail("Số giấy tờ dài quá 50 ký tự.");
    if (passenger.identity_number) {
      const key = passenger.identity_number.toLowerCase();
      if (identities.has(key)) fail(`Số giấy tờ trùng với dòng ${identities.get(key)}.`);
      identities.set(key, line);
    }
    if (passenger.phone && (!/^\+?[0-9]{8,20}$/.test(passenger.phone.replace(/[\s-]/g, "")) || passenger.phone.length > 20)) fail("Số điện thoại không hợp lệ (8–20 chữ số, có thể bắt đầu bằng +).");
    if (type !== "adult" && passenger.identity_number) fail("Form chỉ khai giấy tờ cho người lớn. Để trống số giấy tờ ở dòng trẻ em/em bé.");
    if (type === "infant" && passenger.phone) fail("Để trống điện thoại của em bé; dùng số của người đại diện.");
    passenger.special_request = text(read("special_request"));
    if (passenger.special_request.length > 500) fail("Yêu cầu riêng dài quá 500 ký tự.");
    const contact = enumValue(text(read("is_contact")), { yes: ["Có", "1", "true", "x"], no: ["Không", "0", "false"] });
    if (contact === null) fail("Người đại diện: nhập Có hoặc Không.");
    passenger.is_contact = contact === "yes";
    result.passengers.push(passenger);
  }
  for (const type of ["adult", "child", "infant"] as const) {
    if (counts[type] > context[`${type}_count`]) result.errors.push(`File có ${counts[type]} ${passengerTypeLabels[type].toLowerCase()}, nhưng đơn chỉ đặt ${context[`${type}_count`]}.`);
  }
  const contacts = result.passengers.filter(p => p.is_contact).length;
  if (contacts > 1) result.errors.push("Chỉ chọn một người đại diện trong danh sách.");
  if (contacts === 0) {
    const firstAdult = result.passengers.find(p => p.type === "adult");
    if (firstAdult) firstAdult.is_contact = true;
    result.warnings.push("Chưa chọn người đại diện: dùng người lớn đầu tiên trong file, bạn có thể đổi trên form.");
  }
  const booked = context.adult_count + context.child_count + context.infant_count;
  if (result.passengers.length < booked) result.warnings.push(`File có ${result.passengers.length}/${booked} hành khách. Các vị trí còn thiếu sẽ để trống để bạn nhập tiếp.`);
  return result;
}

/** Match imported records to purchased passenger types, never overwrite a child's type by position. */
export function fillPassengerSlots(passengers: PassengerRow[], context: ImportContext): PassengerRow[] {
  return (["adult", "child", "infant"] as const).flatMap(type => {
    const matching = passengers.filter(p => p.type === type);
    return Array.from({ length: context[`${type}_count`] }, (_, i) => matching[i] ?? emptyPassenger(type));
  });
}

export function passengerCsvTemplate(context: ImportContext): string {
  const headers = Object.values(columns).map(names => names[0]);
  const rows = fillPassengerSlots([], context).map((p, i) => ["", passengerTypeLabels[p.type], "", "", p.type === "adult" ? "CCCD" : "", "", "", "", i === 0 ? "Có" : "Không"]);
  return "\uFEFF" + [headers, ...rows].map(row => row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(",")).join("\r\n");
}
