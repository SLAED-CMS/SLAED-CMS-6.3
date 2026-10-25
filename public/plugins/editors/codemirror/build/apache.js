import { StreamLanguage } from '@codemirror/language'

// The configuration of Apache: a directive opens its line and sections stand in angle brackets
const apache = {
    name: 'apache',
    startState: () => ({ head: true }),
    token(stream, state) {
        if (stream.sol()) state.head = true
        if (stream.eatSpace()) return null
        if (state.head && stream.peek() === '#') {
            stream.skipToEnd()
            return 'comment'
        }
        if (state.head) {
            state.head = false
            if (stream.match(/^<\/?[A-Za-z]+/)) return 'typeName'
            if (stream.match(/^[A-Za-z][\w.]*/)) return 'propertyName'
        }
        if (stream.eat('>')) return 'typeName'
        if (stream.match(/^%\{[^}\s]*\}?/) || stream.match(/^[$%]\d/)) return 'string.special'
        if (stream.match(/^\[[^\]\s]*\]/)) return 'atom'
        if (stream.match(/^"(?:[^"\\]|\\.)*"?/)) return 'string'
        if (stream.match(/^(?:on|off|all|none)(?![\w-])/i)) return 'atom'
        if (stream.match(/^\d+(?![\w.])/)) return 'number'
        if (stream.match(/^(?:!|-[dflsFU](?!\w))/)) return 'operator'
        stream.next()
        stream.eatWhile(/[^\s"%$[>]/)
        return null
    },
    languageData: { commentTokens: { line: '#' } },
}

export const language = () => StreamLanguage.define(apache)
