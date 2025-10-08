# Translation JavaScript Architecture

The translation functionality has been refactored into a modular, maintainable structure.

## File Structure

```
resources/admin/js/
├── translation.js          # Entry point - bootstraps app and event handlers
├── components/
│   └── TranslationModal.js # React modal component
├── hooks/
│   └── useTranslation.js   # Custom hook for translation state management
└── utils/
    ├── api.js              # API utilities and field key normalization
    └── fields.js           # Field manipulation and button creation utilities
```

## Key Components

### 1. Entry Point (`translation.js`)
- Lightweight bootstrap file
- Sets up React app and DOM event listeners
- Initializes ACF field buttons
- Handles translation saving events

### 2. TranslationModal Component (`components/TranslationModal.js`)
- React component using WordPress components (Modal, Button, TextareaControl, Notice)
- Uses `useTranslation` hook for state management
- Handles modal lifecycle, user interactions, and translation saving

### 3. useTranslation Hook (`hooks/useTranslation.js`)
- Encapsulates all translation state logic
- Provides `loadOriginal`, `translate`, and `reset` functions
- Manages loading states and error handling

### 4. API Utilities (`utils/api.js`)
- `cleanFieldKey()` - Normalizes ACF field keys for API calls
- `loadOriginalValue()` - Fetches field values from default language posts
- `translateText()` - Makes REST API calls to DeepL translation endpoint
- `updateACFField()` - Updates ACF field values and triggers change events

### 5. Field Utilities (`utils/fields.js`)
- `copyOriginalToField()` - Copies original field value to current field
- `createTranslationButton()` - Creates and configures translation/copy buttons with event handlers

## Benefits

- **Separation of Concerns**: Each file has a single responsibility
- **Reusability**: Utilities can be imported and used across components
- **Maintainability**: Easier to locate and modify specific functionality
- **Testing**: Individual components and utilities can be tested in isolation
- **Consistency**: Unified API handling and error patterns

## Usage

The refactored code maintains full backward compatibility. All existing functionality works the same way, but the codebase is now more organized and easier to maintain.