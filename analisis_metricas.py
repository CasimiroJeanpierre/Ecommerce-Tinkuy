#!/usr/bin/env python3
"""
Análisis de métricas del proyecto Ecommerce-Tinkuy
- Fan-in / Fan-out de funciones/métodos
- Porcentaje de comentarios, líneas en blanco
- Profundidad de anidamiento condicional
"""

import os
import re
from collections import defaultdict

BASE_DIR = "C:/xampp/htdocs/Ecommerce-Tinkuy"
EXCLUDE = {'vendor', 'node_modules', '.git', '.claude'}

# ─────────────────────────────────────────
# 1. Recolectar todos los archivos PHP
# ─────────────────────────────────────────
php_files = []
for root, dirs, files in os.walk(BASE_DIR):
    dirs[:] = [d for d in dirs if d not in EXCLUDE]
    for f in files:
        if f.endswith('.php'):
            php_files.append(os.path.join(root, f))

print(f"Archivos PHP encontrados: {len(php_files)}")

# ─────────────────────────────────────────
# 2. Métricas globales (tipo cloc)
# ─────────────────────────────────────────
total_lines = 0
blank_lines  = 0
comment_lines = 0
code_lines    = 0

def count_lines(filepath):
    """Cuenta líneas totales, en blanco y comentarios de un archivo PHP."""
    try:
        with open(filepath, encoding='utf-8', errors='ignore') as fh:
            lines = fh.readlines()
    except:
        return 0, 0, 0, 0

    tot = len(lines)
    blank = 0
    comm  = 0
    in_block = False

    for raw in lines:
        stripped = raw.strip()
        if not stripped:
            blank += 1
            continue
        if in_block:
            comm += 1
            if '*/' in stripped:
                in_block = False
            continue
        if stripped.startswith('/*') or stripped.startswith('/**'):
            comm += 1
            if '*/' not in stripped[2:]:
                in_block = True
            continue
        if stripped.startswith('//') or stripped.startswith('#') or stripped.startswith('*'):
            comm += 1
            continue
        # inline /* */
        if '/*' in stripped and '*/' in stripped:
            pass  # cuenta como código con comentario inline → código

    code = tot - blank - comm
    return tot, blank, comm, code

for fp in php_files:
    t, b, c, co = count_lines(fp)
    total_lines   += t
    blank_lines   += b
    comment_lines += c
    code_lines    += co

pct_comment = (comment_lines / total_lines * 100) if total_lines else 0
pct_blank   = (blank_lines   / total_lines * 100) if total_lines else 0
pct_code    = (code_lines    / total_lines * 100) if total_lines else 0

print("\n" + "="*60)
print("  RESUMEN GLOBAL (equivalente CLOC)")
print("="*60)
print(f"  Archivos PHP          : {len(php_files)}")
print(f"  Líneas totales        : {total_lines}")
print(f"  Líneas de código      : {code_lines}  ({pct_code:.1f}%)")
print(f"  Líneas de comentarios : {comment_lines}  ({pct_comment:.1f}%)")
print(f"  Líneas en blanco      : {blank_lines}  ({pct_blank:.1f}%)")
print()

# Estado del proyecto según ratio de comentarios
if pct_comment >= 20:
    estado = "BIEN DOCUMENTADO"
elif pct_comment >= 10:
    estado = "MODERADAMENTE DOCUMENTADO"
elif pct_comment >= 5:
    estado = "POCO DOCUMENTADO"
else:
    estado = "MUY POCO DOCUMENTADO (crítico)"

print(f"  Estado del proyecto   : {estado}")
print(f"  (Umbral recomendado: >=20% comentarios, <=25% lineas en blanco)")
print("="*60)

# ─────────────────────────────────────────
# 3. Fan-in / Fan-out (basado en el cuerpo real de cada función)
# ─────────────────────────────────────────
func_def  = re.compile(r'function\s+(\w+)\s*\(', re.IGNORECASE)
func_call = re.compile(r'\b(\w+)\s*\(')


def strip_strings(src):
    src = re.sub(r'"(?:\\.|[^"\\])*"', '""', src)
    src = re.sub(r"'(?:\\.|[^'\\])*'", "''", src)
    return src


def strip_comments(src):
    src = re.sub(r'/\*.*?\*/', '', src, flags=re.DOTALL)
    src = re.sub(r'(?://|#)[^\n]*', '', src)
    return src


def find_matching(text, open_ch, close_ch, start, depth=1):
    """start apunta justo después de un open_ch; devuelve el índice del close_ch que lo cierra."""
    i = start
    while i < len(text):
        c = text[i]
        if c == open_ch:
            depth += 1
        elif c == close_ch:
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1


all_functions = set()
defined_in = defaultdict(list)   # func -> [files]
calls_from = defaultdict(set)    # func -> set of callers (func names)
calls_to   = defaultdict(set)    # func -> set of callees (func names)

# Paso 1: localizar cada función/método y extraer el texto real de su cuerpo
func_bodies = []  # (filepath, nombre, cuerpo)
for fp in php_files:
    try:
        src = open(fp, encoding='utf-8', errors='ignore').read()
    except:
        continue
    clean = strip_comments(strip_strings(src))

    for m in func_def.finditer(clean):
        name = m.group(1)
        all_functions.add(name)
        defined_in[name].append(fp)

        # Saltar la lista de parámetros (puede contener "(" anidados)
        params_end = find_matching(clean, '(', ')', m.end())
        if params_end == -1:
            continue

        # Saltar el tipo de retorno hasta '{' (con cuerpo) o ';' (abstracto/interface)
        j = params_end + 1
        while j < len(clean) and clean[j] not in '{;':
            j += 1
        if j >= len(clean) or clean[j] != '{':
            continue

        body_end = find_matching(clean, '{', '}', j + 1)
        if body_end == -1:
            continue

        func_bodies.append((fp, name, clean[j + 1:body_end]))

# Paso 2: las llamadas reales de una función son las que aparecen dentro de su cuerpo
for fp, caller, body in func_bodies:
    callees = set(func_call.findall(body)) & all_functions
    callees.discard(caller)
    calls_to[caller].update(callees)
    for callee in callees:
        calls_from[callee].add(caller)

# Fan-in: número de funciones distintas que llaman a esta función
fan_in  = {fn: len(calls_from[fn]) for fn in all_functions}
# Fan-out: número de funciones del proyecto que esta función llama
fan_out = {fn: len(calls_to[fn])   for fn in all_functions}

# Excluir nombres definidos en más de un archivo: si dos métodos de clases
# distintas comparten nombre (p.ej. listar() en dos controladores), sus
# llamadas se fusionan bajo el mismo nombre y el resultado quedaría
# atribuido al primer archivo encontrado, que puede no ser el real.
unique_functions = {fn for fn in all_functions if len(defined_in[fn]) == 1}

# Orden determinista: por valor descendente y, en caso de empate, alfabético
# (el orden de iteración de un set() de strings varía entre ejecuciones por
# el hash randomizado de Python, lo que volvía el "Top 5" no reproducible).
top5_fanin  = sorted(((fn, v) for fn, v in fan_in.items()  if fn in unique_functions), key=lambda x: (-x[1], x[0]))[:5]
top5_fanout = sorted(((fn, v) for fn, v in fan_out.items() if fn in unique_functions), key=lambda x: (-x[1], x[0]))[:5]

print("\n" + "="*60)
print("  TOP 5 – MAYOR FAN-IN (más llamadas recibidas)")
print("="*60)
print(f"  {'#':<4} {'Función/Método':<35} {'Fan-in':>8}")
print(f"  {'-'*4} {'-'*35} {'-'*8}")
for i, (fn, fi) in enumerate(top5_fanin, 1):
    # Find where defined
    files = defined_in.get(fn, [])
    short = os.path.relpath(files[0], BASE_DIR).replace('\\','/') if files else "?"
    print(f"  {i:<4} {fn:<35} {fi:>8}   [{short}]")

print("\n" + "="*60)
print("  TOP 5 – MAYOR FAN-OUT (más funciones llamadas)")
print("="*60)
print(f"  {'#':<4} {'Función/Método':<35} {'Fan-out':>8}")
print(f"  {'-'*4} {'-'*35} {'-'*8}")
for i, (fn, fo) in enumerate(top5_fanout, 1):
    files = defined_in.get(fn, [])
    short = os.path.relpath(files[0], BASE_DIR).replace('\\','/') if files else "?"
    print(f"  {i:<4} {fn:<35} {fo:>8}   [{short}]")

# ─────────────────────────────────────────
# 4. Profundidad de anidamiento condicional
# ─────────────────────────────────────────
COND_KW = re.compile(r'\b(if|elseif|else|foreach|for|while|switch|match|try|catch)\b')

def max_nesting_depth(filepath):
    """Devuelve la profundidad máxima de anidamiento condicional/bucle."""
    try:
        with open(filepath, encoding='utf-8', errors='ignore') as fh:
            lines = fh.readlines()
    except:
        return 0, 0

    depth = 0
    max_depth = 0
    max_line  = 0
    brace_stack = []   # stack of (depth_at_open, is_conditional)

    for lineno, raw in enumerate(lines, 1):
        stripped = raw.strip()
        # skip comments
        if stripped.startswith('//') or stripped.startswith('#') or stripped.startswith('*'):
            continue

        has_cond = bool(COND_KW.search(stripped))

        open_braces  = stripped.count('{') - stripped.count('\\{')
        close_braces = stripped.count('}') - stripped.count('\\}')

        for _ in range(open_braces):
            brace_stack.append(has_cond)
            if has_cond:
                depth += 1
            if depth > max_depth:
                max_depth = depth
                max_line  = lineno

        for _ in range(close_braces):
            if brace_stack:
                was_cond = brace_stack.pop()
                if was_cond:
                    depth = max(0, depth - 1)

    return max_depth, max_line

print("\n" + "="*60)
print("  ANÁLISIS DE PROFUNDIDAD DE ANIDAMIENTO CONDICIONAL")
print("="*60)
print(f"  {'Archivo':<55} {'MaxDepth':>8} {'Línea':>6}")
print(f"  {'-'*55} {'-'*8} {'-'*6}")

depth_results = []
for fp in php_files:
    d, ln = max_nesting_depth(fp)
    short = os.path.relpath(fp, BASE_DIR).replace('\\', '/')
    depth_results.append((short, d, ln))

depth_results.sort(key=lambda x: x[1], reverse=True)

worst = depth_results[:10]
for short, d, ln in worst:
    flag = " ← REFACTORIZAR" if d > 3 else ""
    print(f"  {short:<55} {d:>8} {ln:>6}{flag}")

needs_refactor = [(s,d,l) for s,d,l in depth_results if d > 3]
print(f"\n  Archivos con profundidad > 3 (requieren refactorización): {len(needs_refactor)}")
print(f"  Profundidad máxima global: {depth_results[0][1]} en {depth_results[0][0]} línea {depth_results[0][2]}")

overall_max = depth_results[0][1]
if overall_max > 3:
    print(f"\n  CONCLUSIÓN: El proyecto SUPERA el umbral recomendado de 3 niveles.")
    print(f"             Se deben refactorizar {len(needs_refactor)} archivo(s).")
else:
    print(f"\n  CONCLUSIÓN: El proyecto está dentro del límite recomendado (≤3 niveles).")

print("\n" + "="*60)
print("  DETALLE POR ARCHIVO (cloc-style)")
print("="*60)
print(f"  {'Archivo':<45} {'Total':>6} {'Blank':>6} {'Comment':>8} {'Code':>6}")
print(f"  {'-'*45} {'-'*6} {'-'*6} {'-'*8} {'-'*6}")
file_stats = []
for fp in php_files:
    t, b, c, co = count_lines(fp)
    short = os.path.relpath(fp, BASE_DIR).replace('\\','/')
    file_stats.append((short, t, b, c, co))
file_stats.sort(key=lambda x: x[1], reverse=True)
for short, t, b, c, co in file_stats[:20]:
    print(f"  {short:<45} {t:>6} {b:>6} {c:>8} {co:>6}")
if len(file_stats) > 20:
    print(f"  ... ({len(file_stats)-20} archivos más)")
print("="*60)
