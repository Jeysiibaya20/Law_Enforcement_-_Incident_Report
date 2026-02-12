# 🌍 Language System & Loading Screens - Quick Guide

## What's New

### ✅ 1. Global Language Selector
- **Location**: Top-left corner of every page
- **13 Languages**: English, Spanish, French, German, Italian, Portuguese, Russian, Japanese, Chinese, Korean, Arabic, Hindi, Filipino
- **Auto-Detect**: System automatically detects your browser language
- **Session Saved**: Your choice is remembered during your session

### ✅ 2. Chat Widget Language Selector
- **Location**: In the AI chat widget header (bottom-right)
- **Independent**: Separate from system language
- **6 Languages**: English, Spanish, French, German, Japanese, Filipino
- **Instant**: Click to change chat language immediately

### ✅ 3. Loading Screens
- **System-Wide**: Shows on page transitions
- **Login**: "Logging in..." message with spinner
- **Logout**: "Logging out..." message with spinner
- **Language Change**: "Loading..." message with spinner
- **Professional**: Smooth gradient background with animation

---

## Quick Start

### Change System Language
1. Look at **top-left corner** for language dropdown
2. Click dropdown
3. Select your language (with flag emoji)
4. Page automatically reloads
5. Everything displays in new language

### Change Chat Language
1. Open **floating chat** in bottom-right corner
2. Click **flag icon** in chat header
3. Select language from dropdown
4. Chat responds in new language
5. Chat language is independent from system language

### Experience Loading Screens
1. **Change Language** → See loading screen
2. **Login** → See "Logging in..." screen
3. **Logout** → See "Logging out..." screen

---

## Supported Languages

| Flag | Language | Code |
|------|----------|------|
| 🇺🇸 | English | en |
| 🇪🇸 | Spanish | es |
| 🇫🇷 | French | fr |
| 🇩🇪 | German | de |
| 🇮🇹 | Italian | it |
| 🇵🇹 | Portuguese | pt |
| 🇷🇺 | Russian | ru |
| 🇯🇵 | Japanese | ja |
| 🇨🇳 | Chinese | zh |
| 🇰🇷 | Korean | ko |
| 🇸🇦 | Arabic | ar |
| 🇮🇳 | Hindi | hi |
| 🇵🇭 | Filipino/Tagalog | tl |

---

## Features Explained

### Loading Screen
```
┌─────────────────────────┐
│                         │
│        ⟳ (spinner)      │
│      Loading...         │
│                         │
└─────────────────────────┘
```
- Appears on page transitions
- Duration: ~1 second
- Smooth fade-in/fade-out
- Shows relevant message

### Language Selector (Top-Left)
```
┌──────────────┐
│ 🇺🇸 English  │
│ 🇪🇸 Español  │
│ 🇫🇷 Français │
│ ... more ... │
└──────────────┘
```

### Chat Language Selector (Chat Header)
```
┌────────────────────────────┐
│ 💬 AI | 🇺🇸 | −           │
│ (Assistant title, flag selector, minimize button)
└────────────────────────────┘
```

---

## How It Works

### Session Storage
- Language preference stored in `$_SESSION['language']`
- Applied globally to all pages
- Resets on logout
- Chatbot language separate

### Auto-Detection
1. Checks browser's `Accept-Language` header
2. Extracts primary language code
3. Matches with supported languages
4. Falls back to English if not found

### Loading Screen
- CSS: Gradient background (purple to pink)
- Animation: Rotating spinner
- Message: Updates based on action
- Duration: 1000ms (1 second)

---

## Files

### New Files
- `config/LanguageManager.php` - Core language system

### Modified Files
- `includes/header.php` - Added language selector + chat language button
- `auth/login.php` - Added loading screen
- `auth/logout.php` - Added loading screen

---

## Customization

### Change Loading Screen Colors
Edit `includes/header.php`:
```css
#loadingScreen {
    background: linear-gradient(135deg, #YOUR_COLOR1 0%, #YOUR_COLOR2 100%);
}
```

### Add New Language
Edit `config/LanguageManager.php`:
```php
private static $supported_languages = [
    // ... existing
    'it' => ['name' => 'Italiano', 'flag' => '🇮🇹'],
];
```

### Add Translations
Edit `config/LanguageManager.php`:
```php
'it' => [
    'language' => 'Lingua',
    'logout' => 'Esci',
    // ... more keys
]
```

---

## Tips & Tricks

✅ **Language selector is always visible** on every page
✅ **No page refresh needed** for chat language changes
✅ **System language applies globally** to UI elements
✅ **Chat language is independent** (can be different from system)
✅ **Loading screens enhance** professional appearance
✅ **Works on mobile** with responsive design
✅ **No database needed** for language preferences (session-based)

---

## Troubleshooting

**Language not changing?**
- Refresh the page (Ctrl+F5)
- Check browser language setting
- Try English version first

**Chat language not working?**
- Make sure chat widget is open
- Click flag icon in chat header
- Select from dropdown

**Loading screen stuck?**
- Wait 2-3 seconds (may be loading content)
- Refresh page manually if needed
- Check browser console for errors

---

## Next Steps

1. ✅ Test language selector on home page
2. ✅ Try changing to different languages
3. ✅ Open chat and change chat language
4. ✅ Login to see loading screen
5. ✅ Logout to see loading screen
6. Visit `setup_language_system.php` for full documentation

---

**Version:** 1.0
**Last Updated:** January 14, 2026
**Status:** ✓ Production Ready
