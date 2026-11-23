#!/usr/bin/env python3
"""Compile languages/*.po into .mo files (no gettext tools needed).

Usage: python3 tools/po2mo.py
"""
import ast
import glob
import os
import struct


def parse_po(path):
    entries, msgid, msgstr, field = {}, None, None, None
    def flush():
        if msgid is not None and msgstr:
            entries[msgid] = msgstr
    with open(path, encoding='utf-8') as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith('#'):
                continue
            if line.startswith('msgid '):
                flush()
                msgid, msgstr, field = ast.literal_eval(line[6:]), None, 'id'
            elif line.startswith('msgstr '):
                msgstr, field = ast.literal_eval(line[7:]), 'str'
            elif line.startswith('"'):
                part = ast.literal_eval(line)
                if field == 'id':
                    msgid += part
                else:
                    msgstr += part
    flush()
    return entries


def write_mo(entries, path):
    keys = sorted(entries)
    ids = [k.encode('utf-8') for k in keys]
    strs = [entries[k].encode('utf-8') for k in keys]
    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + sum(len(i) + 1 for i in ids)
    koffsets, voffsets, kpos, vpos = [], [], keystart, valuestart
    for i, s in zip(ids, strs):
        koffsets += [len(i), kpos]
        voffsets += [len(s), vpos]
        kpos += len(i) + 1
        vpos += len(s) + 1
    out = struct.pack('<7I', 0x950412de, 0, n, 28, 28 + 8 * n, 0, 0)
    out += struct.pack('<%dI' % (2 * n), *koffsets) + struct.pack('<%dI' % (2 * n), *voffsets)
    out += b''.join(i + b'\0' for i in ids) + b''.join(s + b'\0' for s in strs)
    with open(path, 'wb') as fh:
        fh.write(out)


if __name__ == '__main__':
    root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'languages')
    for po in sorted(glob.glob(os.path.join(root, '*.po'))):
        entries = parse_po(po)
        write_mo(entries, po[:-3] + '.mo')
        print('%s: %d strings' % (os.path.basename(po), len(entries) - (1 if '' in entries else 0)))
