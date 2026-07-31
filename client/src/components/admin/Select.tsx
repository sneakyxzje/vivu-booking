import { useState, useRef, useEffect } from "react";

interface Option {
  value: string;
  label: string;
}

interface CustomSelectProps {
  value: string;
  onChange: (value: string) => void;
  options: Option[];
  className?: string;
}

export default function CustomSelect({
  value,
  onChange,
  options,
  className = "",
}: CustomSelectProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const selectedOption = options.find((opt) => opt.value === value) || options[0];

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        containerRef.current &&
        !containerRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, []);

  const handleSelect = (val: string) => {
    onChange(val);
    setIsOpen(false);
  };

  return (
    <div className={`relative ${className}`} ref={containerRef}>
      {/* Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="w-full flex items-center justify-between px-3 py-2.5 text-sm border border-gray-200 rounded-md bg-white text-gray-800 outline-none hover:border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-100 cursor-pointer transition-all text-left"
      >
        <span className="font-medium whitespace-nowrap">{selectedOption?.label}</span>
        <svg
          className={`w-4.5 h-4.5 text-gray-450 transition-transform duration-200 shrink-0 ml-2 ${
            isOpen ? "transform rotate-180 text-primary-500" : ""
          }`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </button>

      {/* Options Dropdown list */}
      {isOpen && (
        <div className="absolute right-0 md:left-0 z-50 mt-1.5 min-w-full w-max bg-white border border-gray-200 rounded-md shadow-lg py-1 max-h-60 overflow-y-auto focus:outline-none animate-in fade-in-50 slide-in-from-top-1 duration-100">
          {options.map((option) => {
            const isSelected = option.value === value;
            return (
              <button
                key={option.value}
                type="button"
                onClick={() => handleSelect(option.value)}
                className={`w-full text-left px-3 py-2 text-sm transition-colors cursor-pointer flex items-center justify-between gap-4 ${
                  isSelected
                    ? "bg-primary-50/70 text-primary-700 font-semibold"
                    : "text-gray-700 hover:bg-gray-50 hover:text-gray-900"
                }`}
              >
                <span className="whitespace-nowrap">{option.label}</span>
                {isSelected && (
                  <svg
                    className="w-4 h-4 text-primary-600 shrink-0 ml-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2.5}
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
