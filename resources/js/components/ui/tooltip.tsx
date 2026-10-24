import { Slot } from "@radix-ui/react-slot"
import * as TooltipPrimitive from "@radix-ui/react-tooltip"
import * as React from "react"

import { cn } from "@/lib/utils"

function TooltipProvider({
  delayDuration = 0,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Provider>) {
  return (
    <TooltipPrimitive.Provider
      data-slot="tooltip-provider"
      delayDuration={delayDuration}
      {...props}
    />
  )
}

type Arming = {
  isArmed: boolean
  arm: (byFocus: boolean) => void
  /** Whether the trigger, mounted again as its tooltip arms, takes back the focus that armed it, once. */
  consumeFocus: () => boolean
}

const ArmingContext = React.createContext<Arming | null>(null)

/**
 * A tooltip mounts once someone points at its trigger or focuses it: a page holds dozens, like every commit's actions,
 * and most never show. Until then its trigger renders alone, on the server too. One shown on purpose mounts at once.
 */
function Tooltip({
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Root>) {
  const [hasArmed, setHasArmed] = React.useState(false)
  const takesFocus = React.useRef(false)
  const isShownOnPurpose = props.open === true || props.defaultOpen === true

  // It stays mounted once shown on purpose: closed again, it keeps its trigger, and its content can animate out.
  if (isShownOnPurpose && !hasArmed) {
    setHasArmed(true)
  }

  const isArmed = hasArmed || isShownOnPurpose
  const arming: Arming = {
    isArmed,
    arm: (byFocus) => {
      takesFocus.current = byFocus
      setHasArmed(true)
    },
    consumeFocus: () => {
      const takes = takesFocus.current
      takesFocus.current = false

      return takes
    },
  }

  return (
    <ArmingContext.Provider value={arming}>
      {isArmed ? (
        <TooltipPrimitive.Root data-slot="tooltip" {...props} />
      ) : (
        props.children
      )}
    </ArmingContext.Provider>
  )
}

/** Whether the surrounding tooltip has mounted, for content built from its parts. */
function useTooltipArmed(): boolean {
  return React.useContext(ArmingContext)?.isArmed ?? true
}

function assignRef<T>(ref: React.Ref<T> | undefined, value: T | null): void {
  if (typeof ref === "function") {
    ref(value)
  } else if (ref) {
    ref.current = value
  }
}

const focusable =
  'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'

/** The trigger itself, or the first element it wraps that takes the focus. */
function focusableIn(element: HTMLElement): HTMLElement | null {
  return element.matches(focusable)
    ? element
    : element.querySelector<HTMLElement>(focusable)
}

function TooltipTrigger({
  ref,
  asChild,
  onPointerEnter,
  onFocus,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Trigger>) {
  const arming = React.useContext(ArmingContext)

  if (arming && !arming.isArmed) {
    const Trigger = asChild ? Slot : "button"

    return (
      <Trigger
        ref={ref}
        {...(!asChild && { type: "button" as const })}
        {...props}
        onPointerEnter={(event: React.PointerEvent<HTMLButtonElement>) => {
          onPointerEnter?.(event)
          // A tap would land on a trigger mounted again under the finger, and touch shows no tooltip anyway.
          if (event.pointerType !== "touch") {
            arming.arm(false)
          }
        }}
        onFocus={(event: React.FocusEvent<HTMLButtonElement>) => {
          onFocus?.(event)
          // Only from the keyboard: a click focuses its button too, which arming would mount again before the click
          // lands on it. The focus may land within the trigger, like a button its wrapper passes on.
          if ((event.target as Element).matches(":focus-visible")) {
            arming.arm(true)
          }
        }}
      />
    )
  }

  return (
    <TooltipPrimitive.Trigger
      data-slot="tooltip-trigger"
      ref={(element) => {
        assignRef(ref, element)
        if (element && arming?.consumeFocus()) {
          focusableIn(element)?.focus()
        }
      }}
      asChild={asChild}
      onPointerEnter={onPointerEnter}
      onFocus={onFocus}
      {...props}
    />
  )
}

function TooltipContent({
  className,
  sideOffset = 4,
  children,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Content>) {
  if (!useTooltipArmed()) {
    return null
  }

  return (
    <TooltipPrimitive.Portal>
      <TooltipPrimitive.Content
        data-slot="tooltip-content"
        sideOffset={sideOffset}
        className={cn(
          "bg-popover text-popover-foreground animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 max-w-sm origin-(--radix-tooltip-content-transform-origin) rounded-lg border px-3 py-2 text-xs shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]",
          className
        )}
        {...props}
      >
        {children}
        <TooltipPrimitive.Arrow className="bg-popover fill-popover z-50 size-2.5 translate-y-[calc(-50%-1px)] rotate-45 rounded-[2px] border-r border-b" />
      </TooltipPrimitive.Content>
    </TooltipPrimitive.Portal>
  )
}

export {
  Tooltip,
  TooltipTrigger,
  TooltipContent,
  TooltipProvider,
  useTooltipArmed,
}
