export default {
    content: [
        "./index.html",
        "./src/**/*.{js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            animation: {
                'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                'pulse-short': 'pulse 0.5s ease-in-out 1',
            },
            keyframes: {
                fadeInUp: {
                    '0%': { 
                        opacity: '0', 
                        transform: 'translateY(20px)' 
                    },
                    '100%': { 
                        opacity: '1', 
                        transform: 'translateY(0)' 
                    },
                }
            }
        },
    },
    plugins: [],
}