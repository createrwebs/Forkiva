import vuetify from 'eslint-config-vuetify'
import eslintConfigPrettier from 'eslint-config-prettier/flat'
import unusedImports from 'eslint-plugin-unused-imports'

export default vuetify(
  {
    antfu: false,
    // Nested module repositories are intentionally ignored by the parent Git repo,
    // but their frontend sources must still be linted as part of this application.
    gitignore: false,
    perfectionist: false,
    regexp: false,
    stylistic: false,
    unicorn: false,
  },

  {
    plugins: {
      'unused-imports': unusedImports,
    },

    rules: {
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': 'off',

      'unused-imports/no-unused-imports': 'error',

      'unused-imports/no-unused-vars': [
        'warn',
        {
          vars: 'all',
          varsIgnorePattern: '^_',
          args: 'after-used',
          argsIgnorePattern: '^_',
        },
      ],

      '@typescript-eslint/unified-signatures': 'off',

      curly: 'off',

      'unicorn/prefer-add-event-listener': 'off',
      'unicorn/prefer-structured-clone': 'off',

      'vue/attributes-order': 'off',
      'vue/custom-event-name-casing': 'off',
      'vue/first-attribute-linebreak': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/html-closing-bracket-spacing': 'off',
      'vue/html-indent': 'off',
      'vue/html-quotes': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/no-mutating-props': 'off',
      'vue/padding-line-between-tags': 'off',
      'vue/script-indent': 'off',
    },
  },

  eslintConfigPrettier,
)
