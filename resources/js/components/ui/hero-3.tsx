import React from "react"
import { motion } from "framer-motion"
import { cn } from "@/lib/utils"

export interface AnimatedMarqueeHeroProps {
  tagline: string
  title: React.ReactNode
  description: string
  ctaText: string
  images: string[]
  className?: string
  onCtaClick?: () => void
}

const ActionButton = ({ children, onClick }: { children: React.ReactNode; onClick?: () => void }) => (
  <motion.button
    whileHover={{ scale: 1.05 }}
    whileTap={{ scale: 0.95 }}
    onClick={onClick}
    className="mt-8 px-8 py-3 rounded-full bg-primary text-white font-semibold transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-opacity-75 cursor-pointer"
  >
    {children}
  </motion.button>
)

export const AnimatedMarqueeHero: React.FC<AnimatedMarqueeHeroProps> = ({
  tagline,
  title,
  description,
  ctaText,
  images,
  className,
  onCtaClick,
}) => {
  const FADE_IN_ANIMATION_VARIANTS = {
    hidden: { opacity: 0, y: 10 },
    show: { opacity: 1, y: 0, transition: { type: "spring", stiffness: 100, damping: 20 } },
  }

  const duplicatedImages = [...images, ...images]

  return (
    <section
      className={cn(
        "relative w-full min-h-[90vh] overflow-hidden bg-background flex flex-col items-center justify-center text-center px-4 py-16",
        className
      )}
    >
      <div className="z-10 flex flex-col items-center max-w-4xl mx-auto">
        {/* Tagline */}
        <motion.div
          initial="hidden"
          animate="show"
          variants={FADE_IN_ANIMATION_VARIANTS}
          className="mb-4 inline-block rounded-full border border-border bg-card/60 px-4 py-1.5 text-sm font-medium text-muted-foreground backdrop-blur-sm"
        >
          {tagline}
        </motion.div>

        {/* Main Title */}
        <motion.h1
          initial="hidden"
          animate="show"
          variants={{
            hidden: {},
            show: {
              transition: {
                staggerChildren: 0.1,
              },
            },
          }}
          className="text-4xl sm:text-6xl md:text-7xl font-bold tracking-tight text-foreground leading-[1.1]"
        >
          {typeof title === "string" ? (
            title.split(" ").map((word, i) => (
              <motion.span
                key={i}
                variants={FADE_IN_ANIMATION_VARIANTS}
                className="inline-block"
              >
                {word}&nbsp;
              </motion.span>
            ))
          ) : (
            title
          )}
        </motion.h1>

        {/* Description */}
        <motion.p
          initial="hidden"
          animate="show"
          variants={FADE_IN_ANIMATION_VARIANTS}
          transition={{ delay: 0.4 }}
          className="mt-6 max-w-xl text-base sm:text-lg text-muted-foreground leading-relaxed font-normal"
        >
          {description}
        </motion.p>

        {/* Call to Action Button */}
        <motion.div
          initial="hidden"
          animate="show"
          variants={FADE_IN_ANIMATION_VARIANTS}
          transition={{ delay: 0.5 }}
        >
          <ActionButton onClick={onCtaClick}>{ctaText}</ActionButton>
        </motion.div>
      </div>

      {/* Animated Image Marquee */}
      <div className="relative mt-12 w-full h-48 md:h-64 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_15%,black_85%,transparent)]">
        <motion.div
          className="flex gap-4 w-max"
          animate={{
            x: ["0%", "-50%"],
          }}
          transition={{
            ease: "linear",
            duration: 35,
            repeat: Infinity,
          }}
        >
          {duplicatedImages.map((src, index) => (
            <div
              key={index}
              className="relative aspect-[4/3] h-40 sm:h-48 md:h-56 flex-shrink-0"
              style={{
                rotate: `${(index % 2 === 0 ? -1.5 : 2)}deg`,
              }}
            >
              <img
                src={src}
                alt={`Showcase ${index + 1}`}
                className="w-full h-full object-cover rounded-2xl border border-border"
                loading="lazy"
              />
            </div>
          ))}
        </motion.div>
      </div>
    </section>
  )
}

export default AnimatedMarqueeHero
