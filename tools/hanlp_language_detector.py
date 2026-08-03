"""HanLP-backed language signal detector for blotter descriptions.

HanLP provides NLP tokenization and script analysis here. Translation remains
with the configured translation provider because HanLP is not a translator.
"""
import base64
import sys


def detect_language(text: str) -> str:
    normalized = f" {text.lower()} "
    markers = {
        "tl": (" ang ", " mga ", " hindi ", " ako ", " ikaw ", " siya ", " salamat ", " mayroon ", " paano ", " ano ", " ito ", " bakit ", " saan ", " kailan ", " ngayon ", " kahapon ", " insidente "),
        "es": (" el ", " la ", " los ", " las ", " que ", " una ", " por ", " para ", " gracias "),
        "fr": (" le ", " la ", " les ", " des ", " une ", " avec ", " pour ", " merci "),
        "de": (" der ", " die ", " das ", " und ", " nicht ", " ist ", " danke "),
        "it": (" il ", " lo ", " gli ", " una ", " che ", " per ", " grazie "),
        "pt": (" o ", " os ", " uma ", " que ", " para ", " obrigado "),
        "nl": (" het ", " een ", " zijn ", " niet ", " voor ", " dank "),
        "tr": (" ve ", " bir ", " için ", " değil ", " teşekkür "),
        "vi": (" và ", " một ", " không ", " trong ", " cảm ơn "),
        "id": (" dan ", " yang ", " tidak ", " untuk ", " terima kasih "),
        "ms": (" dan ", " yang ", " tidak ", " untuk ", " terima kasih "),
        "pl": (" nie ", " jest ", " dla ", " oraz ", " dziękuję "),
        "uk": (" і ", " не ", " це ", " для ", " дякую "),
        "ro": (" și ", " este ", " pentru ", " nu ", " mulțumesc "),
        "el": (" και ", " δεν ", " είναι ", " για ", " ευχαριστώ "),
        "fi": (" ja ", " ei ", " on ", " varten ", " kiitos "),
        "sv": (" och ", " inte ", " är ", " för ", " tack "),
        "da": (" og ", " ikke ", " er ", " for ", " tak "),
        "no": (" og ", " ikke ", " er ", " for ", " takk "),
    }
    for language, language_markers in markers.items():
        if any(marker in normalized for marker in language_markers):
            return language

    # Detect writing systems before loading the HanLP model. This keeps short
    # descriptions reliable even when the tokenizer model is still warming up.
    if any("\u0900" <= character <= "\u097f" for character in text):
        return "hi"
    if any("\u0980" <= character <= "\u09ff" for character in text):
        return "bn"
    if any("\u0b80" <= character <= "\u0bff" for character in text):
        return "ta"
    if any("\u0c00" <= character <= "\u0c7f" for character in text):
        return "te"
    if any("\u0e00" <= character <= "\u0e7f" for character in text):
        return "th"
    if any("\u3040" <= character <= "\u30ff" for character in text):
        return "ja"
    if any("\uac00" <= character <= "\ud7af" for character in text):
        return "ko"
    if any("\u0600" <= character <= "\u06ff" for character in text):
        return "ar"
    if any("\u0400" <= character <= "\u04ff" for character in text):
        return "ru"

    # HanLP is deliberately imported and run here for script/token analysis.
    import hanlp
    from hanlp.pretrained import tok

    tokenizer = hanlp.load(tok.COARSE_ELECTRA_SMALL_ZH)
    tokens = tokenizer(text)
    if any(any("\u4e00" <= character <= "\u9fff" for character in token) for token in tokens):
        return "zh"
    return "en"


def main() -> int:
    if len(sys.argv) != 2:
        return 2
    try:
        text = base64.b64decode(sys.argv[1]).decode("utf-8")
        print(detect_language(text), flush=True)
        return 0
    except Exception:
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
