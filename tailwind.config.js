/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
      "./**/*.php",
      "./pages/**/*.php",
      "./partials/**/*.php",
      "./auth/**/*.php",
      "./public/**/*.php",
      "./api-website/**/*.php",
      "./assets/**/*.php",
      "./lib/**/*.php",
      './assets/js/**/*.js',
    ],
    theme: {
      extend: {
        colors: { primary: { DEFAULT: '#38bdf8' } },
        boxShadow: { soft: '0 10px 30px -10px rgba(0,0,0,.25)' },
      },
    },
    safelist: [
      'bg-[#38bdf8]','text-green-600','text-red-500',
      'dark:bg-gray-900','dark:text-gray-100',
      { pattern: /^(bg|text|ring|border)-(red|blue|gray|green|yellow|primary)-(100|200|300|400|500|600|700|800|900)$/ },
    ],
    plugins: [
      require('autoprefixer'),
    ],
  };
  