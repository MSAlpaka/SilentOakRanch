import type { Config } from 'tailwindcss'
import { fontFamily } from 'tailwindcss/defaultTheme'

const config: Config = {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        background: '#f7f4ee',
        primary: '#385a3f',
        accent: '#a6bfa7',
      },
      fontFamily: {
        sans: ['Manrope', ...fontFamily.sans],
      },
    },
  },
  plugins: [],
}

export default config
