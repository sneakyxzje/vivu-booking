import { hasPassengerHeader } from "./passengerImport.ts";
import type { ImportTable } from "./passengerImport.ts";

export const MAX_IMPORT_BYTES = 5 * 1024 * 1024;
const MAX_EXPANDED_BYTES = 20 * 1024 * 1024;

function parseWordTables(xml: string): ImportTable[] {
  if (/<!DOCTYPE|<!ENTITY/i.test(xml)) throw new Error("File Word chứa cấu trúc XML không được hỗ trợ.");
  const doc = new DOMParser().parseFromString(xml, "application/xml");
  if (doc.getElementsByTagName("parsererror").length) throw new Error("Nội dung file Word bị lỗi.");
  const ns = doc.documentElement.namespaceURI;
  if (!ns || !["http://schemas.openxmlformats.org/wordprocessingml/2006/main", "http://purl.oclc.org/ooxml/wordprocessingml/main"].includes(ns)) throw new Error("File không phải tài liệu Word hợp lệ.");
  return Array.from(doc.getElementsByTagNameNS(ns, "tbl")).map((table, i) => {
    const rows = Array.from(table.childNodes).filter((n): n is Element => n.nodeType === 1 && (n as Element).localName === "tr")
      .map(row => Array.from(row.childNodes).filter((n): n is Element => n.nodeType === 1 && (n as Element).localName === "tc")
        .map(cell => Array.from(cell.getElementsByTagNameNS(ns, "p"))
          .map(p => Array.from(p.getElementsByTagNameNS(ns, "t")).map(t => t.textContent ?? "").join(""))
          .join("\n").trim()));
    if (hasPassengerHeader(rows) && (table.getElementsByTagNameNS(ns, "gridSpan").length || table.getElementsByTagNameNS(ns, "vMerge").length)) {
      throw new Error(`Bảng ${i + 1} có ô gộp. Tách ô để mỗi hàng là một hành khách, mỗi cột là một thông tin.`);
    }
    return { name: `Bảng ${i + 1}`, rows };
  }).filter(table => hasPassengerHeader(table.rows));
}

/** Files stay on the device; saving continues through the existing authenticated passenger API. */
export async function readPassengerFile(file: File): Promise<ImportTable[]> {
  if (file.size === 0) throw new Error("File đang trống.");
  if (file.size > MAX_IMPORT_BYTES) throw new Error("File không được vượt quá 5 MB.");
  const extension = file.name.split(".").pop()?.toLowerCase();
  if (!extension || !["xlsx", "csv", "docx"].includes(extension)) {
    throw new Error("Chọn Excel .xlsx, CSV .csv hoặc Word .docx. Với .xls/.doc cũ, hãy lưu lại thành .xlsx/.docx.");
  }
  if (extension === "csv") {
    const { default: Papa } = await import("papaparse");
    let source: string;
    try { source = new TextDecoder("utf-8", { fatal: true }).decode(await file.arrayBuffer()); }
    catch { throw new Error("CSV không dùng mã hóa UTF-8. Trong Excel, chọn Save As → CSV UTF-8 rồi nhập lại."); }
    const parsed = Papa.parse<string[]>(source.replace(/^\uFEFF/, ""), { skipEmptyLines: false, dynamicTyping: false });
    if (parsed.errors.length) throw new Error("Không đọc được CSV. Kiểm tra dấu phân cách và dấu ngoặc kép; lưu file dạng CSV UTF-8.");
    return [{ name: file.name, rows: parsed.data }];
  }

  const buffer = await file.arrayBuffer();
  const { unzipSync, strFromU8 } = await import("fflate");
  let expanded = 0;
  let entries = 0;
  // Inspect the ZIP directory before allocating any decompressed Office content.
  unzipSync(new Uint8Array(buffer), { filter(entry) {
    expanded += entry.originalSize;
    entries++;
    if (expanded > MAX_EXPANDED_BYTES || entries > 1000) throw new Error("File có quá nhiều nội dung. Chỉ giữ bảng danh sách hành khách rồi thử lại.");
    return false;
  } });
  let tables: ImportTable[];
  if (extension === "docx") {
    const files = unzipSync(new Uint8Array(buffer), { filter: entry => entry.name === "word/document.xml" });
    if (!files["word/document.xml"]) throw new Error("File không phải DOCX hợp lệ hoặc đã được đặt mật khẩu.");
    tables = parseWordTables(strFromU8(files["word/document.xml"]));
  } else {
    const worksheets = unzipSync(new Uint8Array(buffer), { filter: entry => /^xl\/worksheets\/[^/]+\.xml$/.test(entry.name) });
    for (const bytes of Object.values(worksheets)) {
      const xml = strFromU8(bytes);
      // Reject sparse sheets with huge coordinates before the reader allocates the grid.
      for (const match of xml.matchAll(/\b(?:r|ref)="([A-Z]+)([0-9]+)(?::([A-Z]+)([0-9]+))?"/g)) {
        const column = match[3] ?? match[1];
        const columnNumber = [...column].reduce((n, char) => n * 26 + char.charCodeAt(0) - 64, 0);
        if (columnNumber > 50 || Number(match[4] ?? match[2]) > 1000) throw new Error("Sheet vượt 1.000 dòng hoặc 50 cột. Chỉ giữ bảng hành khách cần nhập.");
      }
    }
    const { default: readExcel } = await import("read-excel-file/universal");
    const sheets = await readExcel(buffer);
    tables = sheets.map(sheet => ({ name: sheet.sheet, rows: sheet.data.map(row => row.map(cell => {
      if (cell == null || typeof cell === "string" || typeof cell === "number" || typeof cell === "boolean" || cell instanceof Date) return cell;
      throw new Error("Excel chứa giá trị ô không được hỗ trợ.");
    })) }));
  }
  if (!tables.length) throw new Error("Không tìm thấy bảng hành khách. Word cần bảng có hàng tiêu đề Họ và tên, Loại khách.");
  if (tables.length > 20 || tables.some(table => table.rows.length > 1000 || table.rows.some(row => row.length > 50))) {
    throw new Error("File quá nhiều dòng/cột. Chỉ giữ bảng hành khách cần nhập (tối đa 50 người).");
  }
  return tables;
}
