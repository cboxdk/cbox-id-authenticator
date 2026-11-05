import Foundation

@main
struct TextInputEchoTests {
    static func main() {
        var echoes = CboxTextInputEchoes()
        for value in ["L", "La", "Lay", "Layouttest"] { echoes.sent(value) }
        precondition(echoes.acknowledge("L"))
        precondition(echoes.acknowledge("La"))
        precondition(echoes.acknowledge("Layouttest"))
        // The final acknowledged value consumed all earlier outstanding edits.
        precondition(!echoes.acknowledge("Lay"))
        precondition(!echoes.acknowledge("server replacement"))
        echoes.sent("a")
        echoes.sent("")
        echoes.sent("a")
        precondition(echoes.acknowledge("a"))
        precondition(echoes.acknowledge(""))
        precondition(echoes.acknowledge("a"))
        echoes.sent("private text")
        echoes.reset()
        precondition(!echoes.acknowledge("private text"))
        print("PASS: delayed echoes, coalescing, repeated values, authoritative changes, reset")
    }
}
