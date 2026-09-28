import { Tooltip } from "antd";
import { CircleHelp } from "lucide-react";

export default function FieldHelp({ label, children }: { label: string; children: React.ReactNode }) {
  return <Tooltip title={children} trigger={["hover", "focus", "click"]}>
    <button type="button" aria-label={label} className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">
      <CircleHelp size={15} aria-hidden="true" />
    </button>
  </Tooltip>;
}
