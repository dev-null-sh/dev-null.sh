import type { CSSProperties } from "react"
import { Toaster as Sonner, type ToasterProps } from "sonner"

import { useAppearance } from "@/hooks/use-appearance"
import { useFlashToast } from "@/hooks/use-flash-toast"
import { useNewVersionToast } from "@/hooks/use-new-version-toast"

function Toaster({ ...props }: ToasterProps) {
  const { appearance } = useAppearance()

  useFlashToast()
  useNewVersionToast()

  return (
    <Sonner
      theme={appearance}
      className="toaster group"
      position="bottom-right"
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
