import * as React from 'react'

import { cn } from '@/lib/utils'

const Separator = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, orientation = 'horizontal', ...props }, ref) => {
    return (
      <div
        ref={ref}
        role="separator"
        aria-orientation={orientation}
        className={cn(
          'shrink-0 bg-[#e0dacc]',
          orientation === 'vertical' ? 'h-full w-px' : 'h-px w-full',
          className,
        )}
        {...props}
      />
    )
  },
)
Separator.displayName = 'Separator'

export { Separator }
