import { EditorView, keymap, ViewPlugin, Decoration, MatchDecorator } from '@codemirror/view'
import { highlightSpecialChars, drawSelection, dropCursor, rectangularSelection, crosshairCursor, highlightActiveLine } from '@codemirror/view'
import { EditorState, Compartment } from '@codemirror/state'
import { basicSetup } from 'codemirror'
import { indentWithTab, undo, redo, toggleComment, selectAll, history, defaultKeymap, historyKeymap } from '@codemirror/commands'
import { syntaxHighlighting, foldAll, unfoldAll, foldGutter, indentOnInput, defaultHighlightStyle, bracketMatching, foldKeymap } from '@codemirror/language'
import { classHighlighter } from '@lezer/highlight'
import { openSearchPanel, highlightSelectionMatches, searchKeymap } from '@codemirror/search'
import { startCompletion, closeBrackets, autocompletion, closeBracketsKeymap, completionKeymap } from '@codemirror/autocomplete'
import { linter, lintGutter, openLintPanel, nextDiagnostic, lintKeymap } from '@codemirror/lint'

// The basic setup of a text: the same as code without the line numbers and their gutter mark, which a text does not count by
const textSetup = [
    highlightSpecialChars(),
    history(),
    foldGutter(),
    drawSelection(),
    dropCursor(),
    EditorState.allowMultipleSelections.of(true),
    indentOnInput(),
    syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
    bracketMatching(),
    closeBrackets(),
    autocompletion(),
    rectangularSelection(),
    crosshairCursor(),
    highlightActiveLine(),
    highlightSelectionMatches(),
    keymap.of([...closeBracketsKeymap, ...defaultKeymap, ...searchKeymap, ...historyKeymap, ...foldKeymap, ...completionKeymap, ...lintKeymap]),
]

export { EditorView, keymap, ViewPlugin, Decoration, MatchDecorator, EditorState, Compartment, basicSetup, textSetup, indentWithTab, undo, redo, toggleComment, selectAll }
export { syntaxHighlighting, foldAll, unfoldAll, classHighlighter, openSearchPanel, startCompletion, linter, lintGutter, openLintPanel, nextDiagnostic }
