type BadgeVariant = "light" | "solid";
type BadgeSize = "sm" | "md";
type BadgeColor =
  "primary" | "success" | "error" | "warning" | "info" | "light" | "dark";

interface BadgeProps {
  variant?: BadgeVariant; // Light or solid variant
  size?: BadgeSize; // Badge size
  color?: BadgeColor; // Badge color
  startIcon?: React.ReactNode; // Icon at the start
  endIcon?: React.ReactNode; // Icon at the end
  children: React.ReactNode; // Badge content
}

const Badge: React.FC<BadgeProps> = ({
  variant = "solid",
  color = "primary",
  size = "md",
  startIcon,
  endIcon,
  children,
}) => {
  const baseStyles =
    "inline-flex items-center px-2.5 py-0.5 justify-center gap-1 rounded-full font-semibold shadow-xs";

  // Define size styles
  const sizeStyles = {
    sm: "text-theme-xs px-2 py-0.5", // Smaller padding and font size
    md: "text-xs px-2.5 py-1", // Default padding and font size
  };

  // Define color styles for variants
  const variants = {
    light: {
      primary:
        "bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400",
      success:
        "bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500",
      error:
        "bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500",
      warning:
        "bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400",
      info: "bg-blue-light-50 text-blue-light-500 dark:bg-blue-light-500/15 dark:text-blue-light-500",
      light: "bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-white/80",
      dark: "bg-gray-500 text-white dark:bg-white/5 dark:text-white",
    },
    solid: {
      primary: "bg-brand-500 text-white dark:bg-brand-500",
      success: "bg-emerald-600 text-white dark:bg-emerald-500",
      error: "bg-rose-600 text-white dark:bg-rose-500",
      warning: "bg-amber-500 text-white dark:bg-amber-600",
      info: "bg-blue-600 text-white dark:bg-blue-500",
      light: "bg-gray-500 text-white dark:bg-gray-600",
      dark: "bg-gray-800 text-white dark:bg-gray-700",
    },
  };

  // Get styles based on size and color variant
  const sizeClass = sizeStyles[size];
  const colorStyles = variants[variant][color];

  return (
    <span className={`${baseStyles} ${sizeClass} ${colorStyles}`}>
      {startIcon && <span className="me-1">{startIcon}</span>}
      {children}
      {endIcon && <span className="ms-1">{endIcon}</span>}
    </span>
  );
};

export default Badge;
