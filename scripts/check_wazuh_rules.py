#!/usr/bin/env python3
"""Verifica offline las reglas de Wazuh de NovaTech (sin necesidad de levantar Wazuh).

Comprueba que el XML está bien formado y que, para cada línea real de ops/wazuh/samples.log, la regla simple
de mayor nivel que coincide es la esperada (ops/wazuh/samples.expected.txt). Las reglas de correlación
(frequency) se validan con `node scripts/wazuh.mjs logtest`.
  python3 scripts/check_wazuh_rules.py
"""
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

OPS = Path(__file__).resolve().parent.parent / "ops" / "wazuh"


def os_regex(pattern):
    """OS_Regex de Wazuh: '.' es literal y '\\.' es cualquier carácter."""
    pattern = pattern.replace("\\.", "\x00")
    pattern = re.sub(r"(?<!\\)\.", r"\\.", pattern)
    return pattern.replace("\x00", ".")


def get(data, path):
    for key in path.split("."):
        if isinstance(data, dict) and key in data:
            data = data[key]
        else:
            return None
    return data


def load_rules():
    rules = {}
    for rule in ET.parse(OPS / "local_rules.xml").getroot().findall("rule"):
        rules[rule.get("id")] = {
            "level": int(rule.get("level")),
            "correlation": bool(rule.get("frequency") or rule.findtext("if_matched_sid")),
            "if_sid": rule.findtext("if_sid"),
            "decoded": rule.findtext("decoded_as"),
            "fields": [(f.get("name"), f.text) for f in rule.findall("field")],
            "pcre": [r.text for r in rule.findall("regex")],
        }
    return rules


def matches(rules, rule_id, event, raw):
    rule = rules[rule_id]
    if rule["correlation"]:
        return False
    if rule["decoded"] == "json" and not isinstance(event, dict):
        return False
    if rule["if_sid"] and not matches(rules, rule["if_sid"], event, raw):
        return False
    for name, regex in rule["fields"]:
        value = get(event, name)
        if value is None or re.search(os_regex(regex), str(value)) is None:
            return False
    return all(re.search(rx, raw) for rx in rule["pcre"])


def main():
    rules = load_rules()
    expected = [line.strip() for line in (OPS / "samples.expected.txt").read_text().splitlines() if line.strip()]
    samples = [line.rstrip("\n") for line in (OPS / "samples.log").read_text().splitlines() if line.strip()]
    if len(expected) != len(samples):
        sys.exit(f"samples.log tiene {len(samples)} líneas y samples.expected.txt {len(expected)}")

    failures = 0
    covered = set()
    for n, (raw, want) in enumerate(zip(samples, expected), 1):
        event = json.loads(raw)
        hits = [rid for rid in rules if matches(rules, rid, event, raw)]
        best = max(hits, key=lambda i: (rules[i]["level"], i), default=None)
        covered.update(hits)
        ok = best == want
        failures += not ok
        print(f"  {'✓' if ok else '✗'} línea {n:2d}: regla {best} (nivel {rules[best]['level'] if best else '-'})" + ("" if ok else f"  esperada {want}"))

    uncovered = sorted(i for i, r in rules.items() if not r["correlation"] and i not in covered)
    print(f"\nReglas de correlación (validar con wazuh-logtest): {', '.join(i for i, r in rules.items() if r['correlation'])}")
    print(f"Reglas simples sin muestra real: {', '.join(uncovered) or 'ninguna'}")
    print("\nTodo correcto." if failures == 0 else f"\n{failures} línea(s) no coinciden con la regla esperada.")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
