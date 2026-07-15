import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import jsxA11y from 'eslint-plugin-jsx-a11y'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['src/**/*.{js,jsx}'],
    extends: [
      js.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
      jsxA11y.flatConfigs.recommended,
    ],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
      parserOptions: {
        ecmaVersion: 'latest',
        ecmaFeatures: { jsx: true },
        sourceType: 'module',
      },
    },
    rules: {
      'no-unused-vars': ['error', {
        varsIgnorePattern: '^[A-Z_]',
        argsIgnorePattern: '^_',
        caughtErrors: 'none',
        destructuredArrayIgnorePattern: '^_',
      }],
      'no-empty': ['error', { allowEmptyCatch: true }],

      // Reglas de react-hooks v7 muy estrictas — patrones válidos en esta base de código
      'react-hooks/set-state-in-effect': 'off',
      'react-hooks/immutability': 'off',
      'react-hooks/purity': 'off',
      'react-hooks/exhaustive-deps': 'off',

      // Fast-refresh: no aplica restricciones en dev
      'react-refresh/only-export-components': 'off',

      // a11y recien habilitado (2026-07-15): 323 hallazgos preexistentes en el repo, en warn
      // para no romper `pnpm lint` en CI mientras se resuelven incrementalmente. Codigo NUEVO
      // deberia evitar agregar mas -- subir a 'error' cuando el baseline llegue a 0.
      'jsx-a11y/label-has-associated-control': 'warn',
      'jsx-a11y/click-events-have-key-events': 'warn',
      'jsx-a11y/no-static-element-interactions': 'warn',
      'jsx-a11y/no-noninteractive-element-interactions': 'warn',
      'jsx-a11y/no-autofocus': 'warn',

      // Todo el repo ya pasa 'es-CL' explícito en fecha/moneda (Utilidades/formato.js) --
      // esta regla previene que vuelva a colarse una llamada sin locale (dependería del
      // navegador del usuario en vez de forzar el formato chileno).
      'no-restricted-syntax': ['error',
        {
          selector: "CallExpression[callee.property.name=/^(toLocaleDateString|toLocaleTimeString|toLocaleString)$/][arguments.length=0]",
          message: "Pasa el locale explícito: .toLocaleDateString('es-CL', ...) — o mejor, usa formatFecha()/formatearMoneda() de Utilidades/formato.js.",
        },
        {
          selector: "NewExpression[callee.object.name='Intl'][arguments.length=0]",
          message: "Pasa el locale explícito: new Intl.NumberFormat('es-CL', ...) — o mejor, usa formatearMoneda() de Utilidades/formato.js.",
        },
      ],
    },
  },

  // Tests unitarios (Vitest)
  {
    files: ['src/**/*.test.{js,jsx}', 'src/test-utils/**/*.{js,jsx}'],
    languageOptions: {
      globals: { ...globals.browser, ...globals.node },
    },
    rules: {
      'no-unused-vars': ['error', {
        varsIgnorePattern: '^[A-Z_]',
        argsIgnorePattern: '^_',
        caughtErrors: 'none',
      }],
      'no-empty': ['error', { allowEmptyCatch: true }],
    },
  },

  // Tests E2E (Playwright) y configuracion
  {
    files: ['e2e/**/*.{js,jsx}', 'playwright.config.js', 'global-setup.js'],
    languageOptions: {
      globals: { ...globals.node },
    },
    rules: {
      'no-unused-vars': ['error', {
        varsIgnorePattern: '^[A-Z_]',
        argsIgnorePattern: '^_',
        caughtErrors: 'none',
      }],
      'no-empty': ['error', { allowEmptyCatch: true }],
    },
  },
])
