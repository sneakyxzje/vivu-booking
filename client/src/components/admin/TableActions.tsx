import type { ReactNode } from "react";
import { Button, Dropdown, Flex, Typography } from "antd";
import { ChevronDown } from "lucide-react";

export interface ActionItem {
  label: string;
  onClick: () => void;
  icon?: ReactNode;
  variant?: "danger" | "default" | "success" | "warning";
  hint?: string;
  disabled?: boolean;
}
export function TableActions({ actions, label = "Thao tác" }: { id: string | number; actions: ActionItem[]; label?: string }) {
  return <Dropdown trigger={["click"]} placement="bottomRight" menu={{
    items: actions.map((action, index) => ({
      key: String(index), icon: action.icon, disabled: action.disabled, danger: action.variant === "danger",
      label: <Flex vertical><span>{action.label}</span>{action.hint && <Typography.Text type="secondary">{action.hint}</Typography.Text>}</Flex>,
      onClick: ({ domEvent }) => { domEvent.stopPropagation(); if (!action.disabled) action.onClick(); },
    })),
  }}><Button aria-label={label} onClick={(event) => event.stopPropagation()}>Thao tác <ChevronDown size={14} /></Button></Dropdown>;
}
