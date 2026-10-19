import type { CSSProperties } from "react"
import { Toaster as Sonner, type ToasterProps } from "sonner"

import { useAppearance } from "@/hooks/use-appearance"
import { useFlashToast } from "@/hooks/use-flash-toast"
import { useIsMobile } from "@/hooks/use-mobile"
import { useNewVersionToast } from "@/hooks/use-new-version-toast"

const aboveMobileNav = {
  bottom: "calc(var(--mobile-nav-height, 4.5rem + env(safe-area-inset-bottom)) + 0.75rem)",
}

function Toaster({ ...props }: ToasterProps) {
  const { appearance } = useAppearance()
  const isMobile = useIsMobile()

  useFlashToast()
  useNewVersionToast()

  return (
    <Sonner
      theme={appearance}
      className="toaster group"
      position="bottom-right"
      offset={isMobile ? aboveMobileNav : undefined}
      mobileOffset={aboveMobileNav}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
        } as CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
