# 1.4.10.13

Data: 2026-10-08
Commit: 333405b

- Naprawiono licznik wiadomości do pakietów: pierwsze wiadomości nowej osoby już nie przepadają, a nadwyżka ponad próg przechodzi na kolejny pakiet.

## Techniczne

- Licznik wiadomości użytkownika jest aktualizowany atomowo, więc przy wielu wiadomościach naraz żadna nie ginie, a pakiet przyznaje dokładnie jedna z nich.
- Dodano testy licznika (zliczanie, przenoszenie reszty, pierwsza wiadomość, równoległe wiadomości).
