#!/usr/bin/env python3
import argparse
import re
from pathlib import Path

TAG_RE = re.compile(r"<[^>]*\sstyle\s*=\s*\"[^\"]*\"[^>]*>", re.DOTALL)
SCRIPT_BLOCK_RE = re.compile(r"<script\b[\s\S]*?</script>", re.IGNORECASE)
STYLE_ATTR_RE = re.compile(r"\sstyle\s*=\s*\"([^\"]*)\"")
CLASS_ATTR_RE = re.compile(r"\sclass\s*=\s*\"([^\"]*)\"")
TAG_NAME_RE = re.compile(r"^<([a-zA-Z][a-zA-Z0-9:_-]*)")


def slug(prefix: str, idx: int) -> str:
    return f"{prefix}-u{idx:03d}"


def normalize_style(style: str) -> str:
    parts = [p.strip() for p in style.split(";") if p.strip()]
    return "; ".join(parts)


def convert_file(path: Path, prefix: str):
    text = path.read_text(encoding="utf-8")

    style_to_class = {}
    class_to_style = {}
    counter = 1

    def repl_tag(match: re.Match) -> str:
        nonlocal counter
        tag = match.group(0)
        sm = STYLE_ATTR_RE.search(tag)
        if not sm:
            return tag

        style = sm.group(1).strip()
        # Skip dynamic PHP styles
        if "<?" in style:
            return tag

        norm = normalize_style(style)
        if norm not in style_to_class:
            cname = slug(prefix, counter)
            counter += 1
            style_to_class[norm] = cname
            class_to_style[cname] = norm
        cname = style_to_class[norm]

        tag_no_style = STYLE_ATTR_RE.sub("", tag, count=1)

        cm = CLASS_ATTR_RE.search(tag_no_style)
        if cm:
            classes = cm.group(1).strip()
            new_classes = f"{classes} {cname}" if classes else cname
            return CLASS_ATTR_RE.sub(f' class="{new_classes}"', tag_no_style, count=1)

        tm = TAG_NAME_RE.search(tag_no_style)
        if not tm:
            return tag_no_style
        insert_at = tm.end(0)
        return tag_no_style[:insert_at] + f' class="{cname}"' + tag_no_style[insert_at:]

    out = []
    pos = 0
    for sm in SCRIPT_BLOCK_RE.finditer(text):
        html_part = text[pos:sm.start()]
        out.append(TAG_RE.sub(repl_tag, html_part))
        out.append(sm.group(0))
        pos = sm.end()
    out.append(TAG_RE.sub(repl_tag, text[pos:]))
    new_text = "".join(out)

    path.write_text(new_text, encoding="utf-8")

    css_lines = [f"/* Auto-migrated from inline styles in {path.as_posix()} */"]
    for cname, style in class_to_style.items():
        css_lines.append(f".{cname} {{ {style}; }}")

    return len(class_to_style), "\n".join(css_lines) + "\n"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("file")
    parser.add_argument("--prefix", required=True)
    parser.add_argument("--css-out", required=True)
    args = parser.parse_args()

    target = Path(args.file)
    count, css = convert_file(target, args.prefix)
    Path(args.css_out).write_text(css, encoding="utf-8")
    print(f"converted_styles={count}")


if __name__ == "__main__":
    main()
