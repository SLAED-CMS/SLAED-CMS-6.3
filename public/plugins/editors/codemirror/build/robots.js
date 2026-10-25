import { StreamLanguage } from '@codemirror/language'

// The rules for robots: a line that is no directive is marked, since a robot ignores it silently
const robots = {
    name: 'robots',
    startState: () => ({ value: false }),
    token(stream, state) {
        if (stream.sol()) state.value = false
        if (stream.eatSpace()) return null
        if (stream.peek() === '#') {
            stream.skipToEnd()
            return 'comment'
        }
        if (!state.value) {
            if (stream.match(/^[A-Za-z-]+(?=\s*:)/)) return 'propertyName'
            if (stream.eat(':')) {
                state.value = true
                return 'punctuation'
            }
            stream.skipToEnd()
            return 'invalid'
        }
        if (stream.match(/^[*$]/)) return 'operator'
        if (stream.match(/^\d+(?:\.\d+)?(?=\s|#|$)/)) return 'number'
        stream.eatWhile(/[^\s#*$]/)
        return 'string'
    },
    languageData: { commentTokens: { line: '#' } },
}

export const language = () => StreamLanguage.define(robots)
