# 1.4.10.13

Data: 2026-10-08
Commit: 333405b

- Naprawiono licznik wiadomości do pakietów: pierwsze wiadomości nowej osoby już nie przepadają, a nadwyżka ponad próg przechodzi na kolejny pakiet.

## Techniczne

- Licznik wiadomości użytkownika jest aktualizowany atomowo, więc przy wielu wiadomościach naraz żadna nie ginie, a pakiet przyznaje dokładnie jedna z nich.
- Dodano testy licznika (zliczanie, przenoszenie reszty, pierwsza wiadomość, równoległe wiadomości).

# 1.4.10.12

Data: 2026-10-08
Commit: 9760f18

- Pakiety otwierane na stronie znów nie liczą się do dziennej misji otwierania pakietów, tak jak przed zmianą w wersji 1.4.9.35.

# 1.4.10.11

Data: 2026-10-08
Commit: fd8569a

- Karty z dużym przepełnieniem (overflow) znów można ulepszać – poprzednie wersje blokowały to za wcześnie.

# 1.4.10.10

Data: 2026-10-08
Commit: 00e3b47

- Remis w PvP zmienia teraz ranking obu graczy tak samo i nie zawyża już punktów.
- Naprawiono sprawdzanie relacji przed wyprawą: karta ze zbyt niską relacją nie wyruszy na wyprawę, na którą nie powinna.
- Pakiety mają zawsze tę samą numerację w bocie i na stronie.
- Błędnie wpisana liczba przedmiotów (np. „3:abc”) nie jest już traktowana jak cały stos.
- Polecenia ktoto i serwerinfo pokazują daty w twojej strefie czasowej.
- Na stronie można ułożyć galerię tylko z własnych kart.
- Wiele drobnych poprawek, przez które polecenia czasem kończyły się błędem.

# 1.4.10.9

Data: 2026-10-08
Commit: 1bc4daf

- Ze względów bezpieczeństwa bot przyjmuje z linków tylko zwykłe obrazki (PNG, JPG, GIF, WEBP), a inne pliki odrzuca.

# 1.4.10.8

Data: 2026-10-07
Commit: 0ce2e77

- Naprawiono rzadki błąd, przez który obrazek karty nie wysyłał się, gdy bot akurat go tworzył.

# 1.4.10.7

Data: 2026-10-07
Commit: cec4898

- Karty z charakterem Yato nie da się już ponownie przemienić – wcześniej pozwalało to sztucznie podbijać statystyki.
- Po wymianie kart talia i jej moc przeliczają się od razu, więc dobór przeciwników w PvP jest dokładny.
- Na safari boty nie mogą już wygrać karty.
- Naprawiono błąd, przez który zdjęte wyciszenie mogło wrócić.
- Czas odnowienia niektórych poleceń liczy się teraz osobno dla każdego gracza.
- Spacja na końcu ustawienia profilu (np. „styl ”) nie powoduje już błędu.
- Pakiet z niszczeniem kart dokładniej rozpoznaje postacie z twojej listy życzeń.
- Zdrowie nowej karty nigdy nie spada poniżej minimum dla jej rangi.
- Skorygowano szansę na kartę rangi D.
- Na listę ról do samodzielnego nadania nie da się już dodać ról z uprawnieniami administracji.
- Bot działa stabilniej i szybciej dzięki wielu poprawkom błędów i zabezpieczeń.

# 1.4.10.6

Data: 2026-10-07
Commit: 2d2a3de

- Profil, karty i ustawienia odświeżają się od razu po zmianie, bez czekania.
- Drobne poprawki stabilności.

# 1.4.10.5

Data: 2026-10-06
Commit: ea7f37c

- Bot co chwilę daje znać stronie sanakan.pl, że działa, więc strona statusu szybciej zauważa awarię.

# 1.4.10.4

Data: 2026-10-06
Commit: 995c7bb

- Poprawiono literówki w wiadomościach bota i opisach poleceń (np. przy loterii).

# 1.4.10.3

Data: 2026-10-05
Commit: 9db66f7

- Nowe polecenie przedmioty- (items-): lista przedmiotów bez części do tworzenia figurek.
- Zaufane strony połączone z botem mogą dostać dostęp do wybranych danych gracza.

# 1.4.10.2

Data: 2026-10-04
Commit: 79e70f1

- Bot udostępnia raport o swoim stanie (Discord, baza danych, Shinden), z którego korzysta strona statusu.

# 1.4.10.1

Data: 2026-10-04
Commit: afd369b

- Karty Sigma wyglądają teraz jak odwrócone do góry nogami karty Delta i mają tyle samo wariantów ramki.

# 1.4.10.0

Data: 2026-10-04
Commit: eac6ee2

- Karty o jakości Sigma dostały tymczasowy wygląd, dopóki nie powstanie ich własna grafika.
- Bot nie zgłasza już błędu, gdy nie może wysłać wiadomości prywatnej graczowi, który je zablokował.

# 1.4.9.39

Data: 2026-10-04
Commit: 48c1c50

- Poprawki wewnętrzne: bot dokładniej zapisuje, co robi, co ułatwia szukanie błędów.

# 1.4.9.38

Data: 2026-10-04
Commit: 6fb057c

- Poprawki wewnętrzne: bot zapisuje błędy, które wcześniej przemilczał.

# 1.4.9.37

Data: 2026-10-04
Commit: 4a7ff34

- Przy ulepszaniu nie można poświęcić karty z wyprawy, z klatki, z talii, oznaczonej jako ulubiona ani tej samej, którą się ulepsza.
- Przedłużenie wygasłej subskrypcji (globalne emotki, kolor) liczy się od dnia zakupu, a nie od dawnej daty końca.
- Usunięcie tytułu z listy życzeń nie zmniejsza już KC postaci.
- Zmiana charakteru karty kilkoma przedmiotami naraz losuje tyle razy, ile przedmiotów zużyto.
- Odebrane nagrody zapisują się, zanim bot o nich napisze, więc nie przepadają przy błędzie.
- Kupowanie miejsc w galerii i duże zakupy w sklepie nie kończą się już błędem.
- Polecenie isSuper działa tylko dla administracji.

# 1.4.9.36

Data: 2026-10-04
Commit: e2f4bbc

- Strona może pokazać więcej danych z twojego konta: pakiety, przedmioty, misje, limity, talię, listę życzeń, figurki i oznaczenia, a także zmieniać oznaczenia, galerię i ulubioną postać.
- Bot nie pobiera już z linków zbyt dużych obrazków.
- Bot dokładniej sprawdza, czy gracz naprawdę opuścił wszystkie serwery, zanim usunie go z KC postaci.

# 1.4.9.35

Data: 2026-10-03
Commit: 6b5aabc

- Szukanie przeciwnika w PvP nie zawiesza się już, gdy nikogo nie ma – bot prosi, by spróbować później.
- Strony mogą łączyć się z kontem gracza za pomocą osobnych kluczy dostępu.
- Usprawniono kolejkę wykonywania poleceń.

# 1.4.9.34

Data: 2026-10-02
Commit: bbeb0af

- Profil na stronie zawiera identyfikator konta na Discordzie.

# 1.4.9.33

Data: 2026-10-01
Commit: 0394585

- Ochrona przed spamem rozpoznaje jako linki tylko adresy z http:// lub https://, więc rzadziej karze przez pomyłkę.
- Wiadomości z samymi obrazkami nie są już liczone jako ta sama powtarzana wiadomość.

# 1.4.9.32

Data: 2026-10-01
Commit: 838a9b3

- Kary nałożone przez ochronę przed spamem mają opisany powód (np. spam obrazkami, podejrzany link, raid).
- Powiadomienie o banie pokazuje jego powód, jeśli jest znany.

# 1.4.9.31

Data: 2026-10-01
Commit: 1764b2f

- Za pierwszym razem bot ostrzega, że wysyłanie wielu obrazków naraz traktuje jak scam.
- Ręczne włączanie ochrony na kanale działa tylko w wersji testowej bota.

# 1.4.9.30

Data: 2026-09-30
Commit: fb0421c

- Nowa ochrona przed spamem: wiadomości z dużą liczbą obrazków są usuwane, a powtarzanie tego kończy się wyciszeniem lub banem.
- Ochrona lepiej wykrywa podejrzane linki i raidy (wiele kont o tej samej nazwie dołączających naraz).
- Administracja może sprawdzić poleceniem isSuper, czy ochrona działa na danym kanale.
- Na stronie można sortować karty według tego, czy są unikatowe.

# 1.4.9.29

Data: 2026-05-08
Commit: 98daf51

- Loteria nie zawiesza się już po błędzie, a boty nie mogą jej wygrać.
- Bot ponawia dodanie reakcji, gdy Discord za pierwszym razem odmówi.

# 1.4.9.28

Data: 2026-05-08
Commit: 424a227

- Komunikat o braku połączenia z Shindenem podaje dokładniejszą przyczynę.

# 1.4.9.27

Data: 2026-05-08
Commit: b3fdc40

- Komunikaty o braku połączenia z Shindenem (np. przy darmowej karcie) pokazują kod błędu.
- Statystyki z Shindena nie psują się już po cichu, gdy strona nie odpowiada.

# 1.4.9.26

Data: 2026-05-07
Commit: 811ee38

- Profil na stronie zawiera limit kart w galerii.

# 1.4.9.25

Data: 2026-05-07
Commit: 94c940e

- Naprawiono galerię na stronie: pokazywała przypadkowe karty z oznaczeniem galerii zamiast pierwszych w kolejności.
- Strona wie, ile kart w sumie ma oznaczenie galerii.

# 1.4.9.24

Data: 2026-04-17
Commit: 39d4a70

- Nie można już dodać do listy życzeń czegoś, co już na niej jest.

# 1.4.9.23

Data: 2026-04-17
Commit: 160ddce

- Kara za bardzo wysoką lub bardzo niską karmę wpływa teraz także na koszt relacji podczas wyprawy.

# 1.4.9.22

Data: 2026-04-16
Commit: 9d4cc4a

- Statystyki na kartach Delta z najnowszym wariantem ramki mają złoty gradient.

# 1.4.9.21

Data: 2026-04-16
Commit: d2ee4a1

- Punkty zdrowia na kartach Delta z najnowszym wariantem ramki są złote.

# 1.4.9.20

Data: 2026-04-15
Commit: 30e14ec

- Karty Delta dostały nowy wariant ramki, a dotychczasowe ramki zostały odświeżone.

# 1.4.9.19

Data: 2026-04-14
Commit: 6ce7e64

- Na stronie można sortować karty według ID postaci, a przy sortowaniu po KC, tytule i nazwie karty tej samej postaci są obok siebie.

# 1.4.9.18

Data: 2026-04-13
Commit: 9f55cd8

- Skorygowano zmianę karmy na wyprawach ekstremalnych i ultimate hardcore.

# 1.4.9.17

Data: 2026-04-13
Commit: b6d6e1e

- Profil na stronie pokazuje łączne KC i aktywne KC kart w kolekcji.
- Nowa kolejność kart w galerii: ranga, jakość, przepełnienie, postać.
- Sortowanie po obrazku uwzględnia datę jego ustawienia, a alfabetyczne sortowanie oznaczeń jest zawsze takie samo.
- Profil na stronie odświeża się częściej.

# 1.4.9.16

Data: 2026-04-12
Commit: 54b8ce6

- Listy kart na stronie mają stałą kolejność, więc przy przechodzeniu między stronami karty się nie powtarzają ani nie znikają.

# 1.4.9.15

Data: 2026-04-12
Commit: afd47bb

- Ukrywanie nieaktywnych graczy przy listach życzeń uwzględnia teraz ich ostatnią aktywność w grze.

# 1.4.9.14

Data: 2026-04-12
Commit: 56518c0

- Polecenie żdodaj dodaje do listy życzeń kilka postaci lub tytułów naraz.
- Wpisy, które zostają na liście po otrzymaniu karty, dodaje się teraz poleceniem żdodajs.

# 1.4.9.13

Data: 2026-04-12
Commit: 7b5a1bb

- Karta pokazuje obok KC także aktywne KC (w nawiasie).
- Na stronie można sortować karty według aktywnego KC.

# 1.4.9.11

Data: 2026-04-12
Commit: 0574aa1

- Przeliczanie KC przez administrację obejmuje wszystkie postacie.

# 1.4.9.10

Data: 2026-04-12
Commit: 1161345

- Przeliczanie KC przez administrację działa szybciej.

# 1.4.9.9

Data: 2026-04-12
Commit: 4661f43

- Przeliczanie KC przez administrację działa szybciej.

# 1.4.9.8

Data: 2026-04-12
Commit: 7c5c42b

- Przeliczanie KC przez administrację na bieżąco pokazuje, ile jeszcze potrwa.

# 1.4.9.7

Data: 2026-04-12
Commit: 5950235

- Przeliczanie KC przez administrację pokazuje, ile mniej więcej potrwa.

# 1.4.9.6

Data: 2026-04-12
Commit: 8527c93

- Naprawiono odświeżanie KC postaci po sprawdzeniu, kto ją chce.

# 1.4.9.5

Data: 2026-04-12
Commit: c1a1bab

- Szybsze sprawdzanie list życzeń przy nowych kartach.
- Przeliczanie KC przez administrację nie przerywa się już po błędzie.

# 1.4.9.4

Data: 2026-04-12
Commit: 8d99fb7

- Administracja może przeliczyć KC wszystkich postaci.

# 1.4.9.3

Data: 2026-04-12
Commit: 0480509

- Nowe aktywne KC: liczba aktywnych graczy, którzy mają postać na liście życzeń.
- Polecenie lazyp domyślnie uwalnia (zamiast niszczyć) karty, których nie chce żaden aktywny gracz – można to przełączyć.
- Polecenie pakiet może brać pod uwagę tylko aktywne KC.

# 1.4.9.2

Data: 2026-04-12
Commit: 9ffda63

- Nowe polecenie sortuj moje oznaczenia: oznaczenia według ID albo alfabetycznie, także na stronie.
- Tworzenie figurek co jakiś czas daje przedmiot do ustawienia animowanego obrazka.
- Skorygowano cenę animowanego obrazka w sklepie.
- Profil na stronie pokazuje ostatnią aktywność gracza.

# 1.4.9.1

Data: 2026-04-11
Commit: 79c0149

- Bot zapamiętuje aktywność w grze (np. pakiety, wyprawy, zakupy), a znaczek ⚠️ nieaktywnego gracza ją uwzględnia.
- Skorygowano domyślne niszczenie kart w poleceniach lazyp i leniwej darmowej karty.
- Skorygowano wpływ charakteru Yandere na wyprawach i karmę na łatwej wyprawie ultimate.

# 1.4.9.0

Data: 2026-04-07
Commit: 21fd664

- Przebudowano wyprawy ultimate: łatwa ma własne nagrody (głównie części figurek), a relacja karty nie spada na niej poniżej zera.
- Skorygowano nagrody, jakość zdobywanych kart oraz koszt relacji i karmy na wyprawach ultimate.
- Skorygowano karmę za przedmioty przywracające relację.
- Karty z figurek mogą zdobywać doświadczenie na wyprawach.

# 1.4.8.30

Data: 2026-04-07
Commit: 50c787a

- Skorygowano cenę zmiany koloru profilu na stronie.
- Za obrazek tła i nakładki profilu na stronie płaci się tylko raz – kolejne zmiany są darmowe.

# 1.4.8.29

Data: 2026-04-06
Commit: 9b277cb

- Nowe polecenie napraw profil (fix profile): naprawia wygasły obrazek profilu – tło, styl, nakładkę lub ultra nakładkę – z obrazka dołączonego do wiadomości.
- Naprawianie obrazka i ramki karty przyjmuje obrazek jako załącznik.
- Przedmioty ustawiające obrazek i konfiguracja profilu mogą wziąć obrazek z załącznika.

# 1.4.8.28

Data: 2026-04-04
Commit: 2128541

- Nowe tytuły dla graczy z bardzo wysoką i bardzo niską karmą.
- Skorygowano karmę za niszczenie i uwalnianie kart, karmę na wyprawach oraz wpływ charakterów Kamidere i Yandere.
- Bardzo wysoka lub bardzo niska karma podnosi koszt relacji na wyprawach.

# 1.4.8.27

Data: 2026-04-04
Commit: 556b276

- Strona szybciej wczytuje listy kart i profile.

# 1.4.8.26

Data: 2026-04-04
Commit: 1dd53e4

- Profil na stronie pokazuje statystyki PvP.

# 1.4.8.25

Data: 2026-04-03
Commit: ae2306f

- Statystyki profilu na stronie liczą się szybciej i pokazują karty ultimate z przepełnieniem.

# 1.4.8.24

Data: 2026-04-03
Commit: 8d94087

- Profil na stronie pokazuje liczbę kart unikatowych.

# 1.4.8.23

Data: 2026-04-02
Commit: 5e4e9f4

- Poprawiono statystyki na profilu na stronie.

# 1.4.8.22

Data: 2026-04-02
Commit: 79af394

- Profil na stronie pokazuje więcej statystyk, m.in. najsilniejszą kartę, łączne restarty i przepełnienia.

# 1.4.8.21

Data: 2026-04-02
Commit: 4ffda5b

- Profil na stronie pokazuje kartę z największą liczbą restartów.

# 1.4.8.20

Data: 2026-04-02
Commit: c0c2964

- Profil na stronie pokazuje moc kart oraz liczbę kart z własnym obrazkiem, animacją i ramką.

# 1.4.8.19

Data: 2026-03-30
Commit: f091b83

- Karta ultimate na rynku może czasem przynieść przedmiot zwiększający zdrowie.
- Skorygowano jakość części figurek z rynku.

# 1.4.8.18

Data: 2026-03-28
Commit: 6c03a67

- Przepełnienie mocy liczy się jak wyższa jakość także przy doświadczeniu, statystykach i wyprawach.
- Poprawiono ulepszanie kart z przepełnieniem.

# 1.4.8.17

Data: 2026-03-22
Commit: ea63991

- Na stronie można sortować karty według przepełnienia, zmęczenia i klątwy.

# 1.4.8.16

Data: 2026-03-21
Commit: a137a2a

- Trzy nowe warianty ramki dla kart Delta.

# 1.4.8.15

Data: 2026-03-21
Commit: d299730

- Linki do kart prowadzą do nowej strony Alter.
- Statystyki kart na profilu nie liczą kart ultimate jako SSS i mają osobną ikonkę dla kart ultimate.

# 1.4.8.14

Data: 2026-03-21
Commit: 8d8d73e

- Liczba kart pokazuje osobno karty ultimate według jakości, także na stronie.

# 1.4.8.13

Data: 2026-03-20
Commit: a7144d9

- Administracja może zmieniać parametry kart.

# 1.4.8.12

Data: 2026-03-19
Commit: 0249bf5

- Lista wszystkich kart na stronie pokazuje właścicieli.

# 1.4.8.11

Data: 2026-03-19
Commit: 116ca25

- Listy kart na stronie pokazują właścicieli i można je filtrować po postaciach.

# 1.4.8.10

Data: 2026-03-18
Commit: 486009b

- Strona widzi przepełnienie mocy kart ultimate.

# 1.4.8.9

Data: 2026-03-18
Commit: 1a8a86c

- Poprawiono wygląd listy stron bota.

# 1.4.8.8

Data: 2026-03-18
Commit: 2402d77

- Lista stron bota zawiera stronę Alter.

# 1.4.8.7

Data: 2026-03-18
Commit: 68bec3f

- Skorygowano jakość części figurek z rynku.

# 1.4.8.6

Data: 2026-03-13
Commit: bfa43aa

- Skorygowano nagrody z rynku dla kart ultimate.

# 1.4.8.5

Data: 2026-03-12
Commit: 6e5ca09

- Na rynku przepełnienie mocy karty ultimate liczy się jak wyższa jakość.

# 1.4.8.4

Data: 2026-03-12
Commit: 2f06ea8

- Karty ultimate mogą iść na rynek i czarny rynek – przynoszą więcej przedmiotów i czasem część figurki.

# 1.4.8.3

Data: 2026-03-12
Commit: d2afee9

- Poprawiono odświeżanie zapisanego profilu.

# 1.4.8.2

Data: 2026-03-08
Commit: d7d11f6

- Kolejny wariant ramki dla kart Delta.

# 1.4.8.1

Data: 2026-03-08
Commit: 258bee7

- Poprawiono animacje.

# 1.4.8.0

Data: 2026-03-08
Commit: f23a586

- Profil wysyła się szybciej, a bardzo duży przychodzi jako link.
- Skorygowano czas odnowienia profilu.

# 1.4.7.20

Data: 2026-03-08
Commit: 0f924f5

- Animowane karty i awatary ruszają się też na profilu i innych obrazkach bota.

# 1.4.7.19

Data: 2026-02-28
Commit: c4d6566

- Poprawiono wczytywanie animowanych obrazków.

# 1.4.7.18

Data: 2026-02-28
Commit: f73a3d7

- Animowane karty mają lepszą jakość i mniejszy rozmiar.

# 1.4.7.17

Data: 2026-02-23
Commit: 82c6b24

- Poprawiono animacje kart.

# 1.4.7.16

Data: 2026-02-23
Commit: b04efa4

- Poprawiono zużywanie kamery na kartach Omega.

# 1.4.7.15

Data: 2026-02-23
Commit: 9763296

- Karty Omega mogą mieć animowany obrazek.

# 1.4.7.14

Data: 2026-02-22
Commit: 2cd3445

- Ostateczna wersja wariantu ramki Lambda.
- Karty Omega mają animowaną ramkę.

# 1.4.7.13

Data: 2026-02-19
Commit: 62b46e5

- Poprawiono grafikę wariantu ramki Lambda.

# 1.4.7.12

Data: 2026-02-19
Commit: 25ec02d

- Pierwszy wariant ramki dla kart Lambda.

# 1.4.7.11

Data: 2026-01-29
Commit: 8554d93

- Lista graczy, którzy chcą karty, może pominąć nieaktywnych, a nieaktywni są oznaczeni ⚠️.

# 1.4.7.10

Data: 2025-10-03
Commit: 292ec36

- Przepełnienie mocy nie resetuje już wariantu ramki.

# 1.4.7.9

Data: 2025-09-15
Commit: da7a084

- Nowe polecenie dla administracji chinfo: pokazuje ustawienia kanału.

# 1.4.7.8

Data: 2025-09-15
Commit: 7666ade

- Administracja może wskazać kanał w poleceniach ustawiających kanały ignorowane i bez doświadczenia.

# 1.4.7.7

Data: 2025-08-08
Commit: 6ee67f2

- Kolejny wariant ramki dla kart Delta.

# 1.4.7.6

Data: 2025-08-06
Commit: 78b29ff

- Kropla krwi nie spacza już karty, która jest spaczona.

# 1.4.7.5

Data: 2025-07-07
Commit: 78ff6c4

- Poprawiono położenie tekstu na obrazku poziomu i na kartach.

# 1.4.7.4

Data: 2025-06-29
Commit: 36e49e8

- Zbyt duży obrazek karty jest odrzucany z komunikatem.

# 1.4.7.3

Data: 2025-06-27
Commit: 5dc5104

- Nowe polecenie rkolor (rcolor): kupno koloru nicku z listy przygotowanej przez serwer, za TC, na miesiąc.
- Poprawki wewnętrzne i aktualizacja bota.

# 1.4.7.1

Data: 2025-05-29
Commit: 2842408

- Po oddaniu karty jej obrazek generuje się od nowa.

# 1.4.7.0

Data: 2025-05-29
Commit: b078fab

- Nowe gwiazdki na kartach: więcej rodzajów i kolorów, a gwiazdki za restarty sięgają dużo dalej.
- Zostały tylko pełne i puste style gwiazdek.
- Skorygowano bonus do statystyk z restartów i gwiazdek.

# 1.4.6.16

Data: 2025-05-28
Commit: 44c2123

- Poprawiono kończenie figurki.

# 1.4.6.15

Data: 2025-05-26
Commit: 6f54212

- Karta ultimate może zamiast awansu na wyższą jakość dostać przepełnienie mocy (+1, +2 itd.).

# 1.4.6.14

Data: 2025-05-26
Commit: 6a80edd

- Nowa jakość figurek: Eta (η), z wieloma wariantami ramki.
- Nowy przedmiot: taśma izolacyjna, która zmienia wariant ramki karty. Można ją kupić w sklepie, a ukończenie figurki Eta daje ją w prezencie.
- Kolejny wariant ramki dla kart Delta.

# 1.4.6.13

Data: 2025-05-24
Commit: b16ab18

- Pierwszy wariant ramki dla kart Delta.

# 1.4.6.12

Data: 2025-05-24
Commit: 3d37bf8

- Usunięto stare oznaczenia kart i polecenie przywróć oznaczenie.

# 1.4.6.11

Data: 2025-05-01
Commit: 4e78092

- Poprawiono nazwy kanałów w logu kanałów głosowych.

# 1.4.6.10

Data: 2025-05-01
Commit: 93f8a1d

- Poprawiono log kanałów głosowych.

# 1.4.6.9

Data: 2025-05-01
Commit: 1dc664d

- Administracja widzi w logach wejścia na kanały głosowe, wyjścia z nich i przejścia między nimi.

# 1.4.6.8

Data: 2025-04-18
Commit: c335887

- Długa lista figurek dzieli się na strony i trafia na PW.

# 1.4.6.7

Data: 2025-04-17
Commit: fe355c7

- Długie listy dzielą się na mniejsze wiadomości, żeby Discord ich nie obcinał.

# 1.4.6.6

Data: 2025-03-23
Commit: 440cd11

- Żart z loterii obejmuje więcej graczy, a na prima aprilis zdarza się częściej.

# 1.4.6.5

Data: 2025-03-06
Commit: c5f865a

- Poprawki wewnętrzne.

# 1.4.6.4

Data: 2025-02-14
Commit: 3f91f3e

- Żart na loterii: czasem ktoś dostaje na PW wiadomość, że czegoś nie wygrał.

# 1.4.6.3

Data: 2025-01-18
Commit: 3ce395d

- Dodatkowy atak z kropli krwi ma górny limit.

# 1.4.6.2

Data: 2025-01-07
Commit: 78c2c93

- Poprawiono regenerację zmęczenia kart.

# 1.4.6.1

Data: 2025-01-07
Commit: adf1583

- Nowe polecenie można dostać? (ica): sprawdza, czy postać jest w twojej puli losowania.

# 1.4.6.0

Data: 2024-12-27
Commit: 08a593b

- Losowanie postaci do pakietów i darmowych kart działa szybciej i rzadziej zawodzi przez Shindena.

# 1.4.5.74

Data: 2024-11-21
Commit: dddbecc

- Administracja widzi statystyki używania emotek.
- Na prima aprilis każda postać ma te same losowe KC we wszystkich miejscach.

# 1.4.5.73

Data: 2024-11-20
Commit: 705e127

- Nowe rodzaje pakietów z loterii: mały i średni.

# 1.4.5.72

Data: 2024-11-20
Commit: 0bca9f3

- Wyniki kilku losowań na loterii są zsumowane w jednej wiadomości.

# 1.4.5.71

Data: 2024-11-20
Commit: c80f410

- Na loterii można użyć kilku biletów naraz.

# 1.4.5.70

Data: 2024-11-18
Commit: db4277a

- Bot ponawia wysłanie odpowiedzi, gdy Discord za pierwszym razem jej nie przyjmie.
- Nowe polecenie pojedynek testowy (test duel).

# 1.4.5.69

Data: 2024-11-17
Commit: efad6f9

- Pakiety z tytułu można kupić kilka naraz.

# 1.4.5.68

Data: 2024-11-17
Commit: ebcdaae

- Lista figurek oznacza ukończone figurki 🎖️, a numer karty z figurki prowadzi do strony.
- Skorygowano cenę kropli krwi waifu w sklepie.

# 1.4.5.67

Data: 2024-11-17
Commit: 90e9aa1

- PvP nie dobiera już przeciwnika, który nie ma kart w talii.

# 1.4.5.66

Data: 2024-11-17
Commit: 8486299

- Karty na wyprawie nie można dodać do talii.

# 1.4.5.65

Data: 2024-11-17
Commit: f00dfe7

- Karty ultimate są silniejsze w PvP.
- Karta z talii nie może iść na wyprawę.

# 1.4.5.64

Data: 2024-11-17
Commit: 9d610bd

- Talia do PvP ma limit kart, a skorygowano też dopuszczalną siłę talii.
- Tańsze niektóre przedmioty w sklepie i nowy pakiet do kupienia.
- Polecenie czego brak informuje, gdy masz już wszystkie postacie z tytułu.

# 1.4.5.63

Data: 2024-11-16
Commit: 9b33fe8

- Lista z polecenia czego brak nie ma powtórzeń.

# 1.4.5.62

Data: 2024-11-16
Commit: fe252ed

- Nowe polecenie czego brak (widh): wysyła na PW listę postaci z tytułu, których nie masz, z opcją pominięcia tych z listy życzeń.

# 1.4.5.61

Data: 2024-11-13
Commit: d68fb15

- Nowy wygląd kart Epsilon.

# 1.4.5.60

Data: 2024-11-12
Commit: c0801bc

- Poprawiono ramki kart Theta.

# 1.4.5.59

Data: 2024-11-11
Commit: 0bbc416

- Nowy wygląd kart Beta.

# 1.4.5.58

Data: 2024-11-02
Commit: dc32460

- Wygrane karty z loterii są posortowane i pokazane bez statystyk.

# 1.4.5.57

Data: 2024-11-02
Commit: 8995f23

- Doświadczenie można przenieść także na kartę ultimate – dostaje połowę.

# 1.4.5.56

Data: 2024-10-27
Commit: df593fb

- Przedmioty dające doświadczenie działają słabiej na kartach ultimate.

# 1.4.5.55

Data: 2024-10-22
Commit: c7e4149

- Bardzo krótka wyprawa mocno męczy kartę i obniża relację.
- Klątwy pojawiają się tylko po dłuższych wyprawach.
- Skorygowano punkty konstrukcji części figurek.

# 1.4.5.54

Data: 2024-10-21
Commit: 2017063

- Można mieć kilka figurek tej samej postaci.
- Skorygowano punkty konstrukcji części figurek.

# 1.4.5.53

Data: 2024-10-15
Commit: 524f6e1

- Polecenie naprawiania obrazków obsługuje też linki imgur.com.

# 1.4.5.52

Data: 2024-10-15
Commit: 4e84514

- Poprawki wewnętrzne.

# 1.4.5.51

Data: 2024-10-15
Commit: a658114

- Poprawki wewnętrzne.

# 1.4.5.50

Data: 2024-10-15
Commit: 1f135fc

- Polecenie naprawiania obrazków obsługuje nowo wygasłe obrazki z imgur, a jedną kartę można naprawić więcej razy.

# 1.4.5.49

Data: 2024-10-01
Commit: 5b00f48

- Poprawiono dodawanie kart do wymiany.

# 1.4.5.48

Data: 2024-09-21
Commit: cb2a98a

- Poprawiono pasek w statystykach z Shindena.

# 1.4.5.47

Data: 2024-09-14
Commit: dfa8b2f

- Odwrócenie karmy zamienia też charakter nieukończonych figurek Yami i Raito.

# 1.4.5.46

Data: 2024-09-12
Commit: 16e5293

- Wyszukiwanie kart z tytułu może ukryć postacie, które już masz.
- Czas, po którym karta traci siły na wyprawie, pokazuje się w twojej strefie czasowej.

# 1.4.5.45

Data: 2024-09-10
Commit: 09fc240

- Czas do kolejnych drobnych, zaskórniaków, darmowej karty i rynku pokazuje się w twojej strefie czasowej.

# 1.4.5.44

Data: 2024-09-10
Commit: a5f61d0

- Czas końca loterii i polowania pokazuje się w twojej strefie czasowej.

# 1.4.5.43

Data: 2024-08-23
Commit: 4787f10

- Strona ma listę kart ultimate.
- Skorygowano zmęczenie karty po wymianie.

# 1.4.5.42

Data: 2024-08-10
Commit: 6b2adc9

- Karta otrzymana w wymianie przychodzi zmęczona i z obniżoną relacją.
- Poprawiono regenerację zmęczenia.
- Bardzo zmęczonej karty nie można zniszczyć ani uwolnić.

# 1.4.5.41

Data: 2024-08-01
Commit: f4c915c

- Administracja może ustawić dzienny limit pakietów za aktywność.
- Skorygowano nagrody z loterii i wydarzenia na wyprawach.

# 1.4.5.40

Data: 2024-07-23
Commit: 5908758

- Poprawki wewnętrzne.

# 1.4.5.39

Data: 2024-07-21
Commit: 693e476

- Polecenie ofiaruj nie działa na kartę, która jest na wyprawie.

# 1.4.5.38

Data: 2024-07-05
Commit: 75e1e00

- Poprawki wewnętrzne.

# 1.4.5.37

Data: 2024-07-01
Commit: 11f3dad

- Poprawiono liczenie kart tworzonych w druciarstwie danego dnia.

# 1.4.5.36

Data: 2024-06-30
Commit: 198053c

- Drobna poprawka.

# 1.4.5.35

Data: 2024-06-30
Commit: 1e5e4c0

- Uproszczono wyliczanie ceny druciarstwa.

# 1.4.5.34

Data: 2024-06-17
Commit: 35894ea

- Poprawiono wyliczanie ceny druciarstwa.

# 1.4.5.33

Data: 2024-06-17
Commit: db995c2

- Nowe ceny druciarstwa: każda kolejna para kart tworzona tego samego dnia kosztuje więcej.

# 1.4.5.32

Data: 2024-06-02
Commit: 2aa7abb

- Skorygowano wyprawę ultimate hardcore.

# 1.4.5.31

Data: 2024-06-01
Commit: 4f9f8f7

- Skorygowano wyprawę ultimate hardcore.

# 1.4.5.30

Data: 2024-06-01
Commit: 2c15e9b

- Skorygowano karmę za niszczenie i uwalnianie kart, koszt druciarstwa i wyprawy ultimate.

# 1.4.5.29

Data: 2024-05-25
Commit: 6fe3940

- W trakcie wymiany nie można zacząć innej wymiany.
- Karta Yami nie trafi w wymianie do gracza z dobrą karmą, a Raito do gracza ze złą.

# 1.4.5.28

Data: 2024-05-21
Commit: 6323a25

- Skorygowano zmęczenie z wypraw i wartość niektórych przedmiotów.
- Statystyki pokazują liczbę kart z druciarstwa.

# 1.4.5.27

Data: 2024-05-18
Commit: d9ff120

- Poprawiono najwyższy rodzaj gwiazdek.

# 1.4.5.26

Data: 2024-05-08
Commit: f38728d

- Bot pisze, dlaczego karta nie może iść na wyprawę.

# 1.4.5.25

Data: 2024-04-28
Commit: d1bd519

- Zmęczenie karty widać w jej opisie i na stronie.

# 1.4.5.24

Data: 2024-04-27
Commit: d81f7b7

- Nowe zmęczenie kart: wyprawy męczą karty, a odpoczynek je regeneruje. Zmęczona karta przynosi mniej, a przepracowana nie może iść na wyprawę.
- Karty Dandere męczą się wolniej.

# 1.4.5.23

Data: 2024-04-19
Commit: 4859861

- Poprawiono opis z kryształowej kuli i lupy.
- Poprawiono blokadę zmiany charakteru przez klątwę.

# 1.4.5.22

Data: 2024-04-18
Commit: 234c3b5

- Informacja o klątwie pojawia się na początku wiadomości z wyprawy.

# 1.4.5.21

Data: 2024-04-18
Commit: cb7e5e1

- Nowe klątwy: karta może zostać spaczona na trudniejszych wyprawach – np. blokada krwi, zmiany charakteru, wypraw lub jedzenia, odwrócone działanie przedmiotów, mniej doświadczenia albo słabsze statystyki.
- Nowy przedmiot: lupa, która pokazuje klątwę ciążącą na karcie. Można ją wytworzyć z przepisu.
- Skorygowano wydarzenia, przedmioty z wypraw i nagrody z loterii.

# 1.4.5.20

Data: 2024-04-15
Commit: c26cb89

- Poprawki wewnętrzne.

# 1.4.5.19

Data: 2024-04-14
Commit: 7361850

- Nowy rodzaj gwiazdek na kartach.
- Figurka z wszystkimi częściami nie pokazuje już aktywnej części.

# 1.4.5.18

Data: 2024-04-08
Commit: 46dcae2

- Pakiet z tytułu odrzuca tytuły, które są reklamami.

# 1.4.5.17

Data: 2024-04-08
Commit: dc9d726

- Skorygowano ceny pakietów i przedmiotów w sklepie.
- W sklepie można kupić zmianę gwiazdek i własną ramkę.
- Pakiet z tytułu odrzuca tytuły bez typu i teledyski, a wystarczy mu mniej postaci w tytule.

# 1.4.5.16

Data: 2024-04-06
Commit: 6e47985

- Pakiety za aktywność w wątkach też uwzględniają kanały bez doświadczenia.

# 1.4.5.15

Data: 2024-04-06
Commit: 0e838dd

- Wątki na Discordzie mają te same ustawienia doświadczenia co kanał, w którym powstały.

# 1.4.5.14

Data: 2024-04-02
Commit: 0ae5661

- Poprawiono przedmioty z jednej z wypraw.

# 1.4.5.13

Data: 2024-04-02
Commit: 2834013

- Skorygowano, gdzie i od jakiego poziomu można używać niektórych poleceń.

# 1.4.5.12

Data: 2024-04-02
Commit: 2368d49

- Wpis na liście życzeń może zostać na niej po otrzymaniu karty.

# 1.4.5.11

Data: 2024-04-02
Commit: 836aaf4

- Nowe emotki w reakcjach bota.

# 1.4.5.10

Data: 2024-04-01
Commit: abf65e4

- Naprawiono ustawianie animowanego obrazka kamerą.

# 1.4.5.9

Data: 2024-03-31
Commit: c97fd0a

- Prima aprilis: tego dnia KC na kartach są losowe, a bot częściej dodaje reakcje.

# 1.4.5.8

Data: 2024-03-28
Commit: 2a0ba02

- Skorygowano wartość przedmiotów.

# 1.4.5.7

Data: 2024-03-27
Commit: 6ac26b4

- Skorygowano karmę za przedmioty i za charaktery kart.

# 1.4.5.6

Data: 2024-03-26
Commit: f085bdd

- Poprawiono doświadczenie zdobywane na wyprawach.

# 1.4.5.5

Data: 2024-03-25
Commit: 4d94726

- Kartę ultimate można ulepszyć, poświęcając inną kartę ultimate tej samej jakości razem z jej figurką.

# 1.4.5.4

Data: 2024-03-24
Commit: c2dd569

- Krótszy zapis walki w pojedynkach.

# 1.4.5.3

Data: 2024-03-23
Commit: 6d40ecb

- Polecenie ofiaruj pozwala za 12 kropli krwi zmienić anioła lub demona w boga.
- Skorygowano wydarzenia i przedmioty z wypraw.

# 1.4.5.2

Data: 2024-03-22
Commit: 9240106

- Bardzo krótkie wyprawy nie mają już wydarzeń.
- Poprawiono rzucanie klątwy przez krople krwi.

# 1.4.5.1

Data: 2024-03-22
Commit: 224787b

- Poprawiono krótkie wyprawy.
- Skorygowano krople krwi.

# 1.4.5.0

Data: 2024-03-20
Commit: 950a606

- Druciarstwo tworzy karty dowolnej rangi, ale nie można ich wymieniać.
- Nowy przepis: zmiana parametrów karty.
- Skorygowano koszt zmiany karty na wymienialną (taniej także dla graczy ze złą karmą) i koszt druciarstwa.
- Skorygowano przedmioty z wypraw.

# 1.4.4.53

Data: 2024-03-20
Commit: e482fdd

- Nowy wygląd kart Alpha.

# 1.4.4.52

Data: 2024-03-19
Commit: e1dcb18

- Przebudowano wydarzenia na wyprawach – każda wyprawa ma własne szanse na wydarzenia.

# 1.4.4.51

Data: 2024-03-18
Commit: dafadfc

- Poprawiono położenie tekstu na profilu i na obrazku poziomu.

# 1.4.4.50

Data: 2024-03-17
Commit: 63383e5

- Semi-administracja i testerzy nie mają czasu odnowienia poleceń.

# 1.4.4.49

Data: 2024-03-17
Commit: 46cb95a

- Nowy przepis: zmiana charakteru.
- Skorygowano przedmioty i karmę na wyprawach.
- Karty postaci spoza Shindena są unikatowe.

# 1.4.4.48

Data: 2024-03-15
Commit: 3121958

- Nowy przedmiot: magiczna rózga, która pozwala stworzyć kartę postaci spoza Shindena.

# 1.4.4.47

Data: 2024-03-14
Commit: de41d0e

- Wyszukiwanie kart na stronie nie szuka już po fragmencie numeru karty.

# 1.4.4.46

Data: 2024-03-14
Commit: e3617ee

- Uwolnienie karty z oznaczeniem kosz po wyprawie może czasem dać CT.

# 1.4.4.45

Data: 2024-03-13
Commit: 453e4ed

- Statystyki pokazują łączną liczbę restartów na kartach.

# 1.4.4.44

Data: 2024-03-13
Commit: b98d66e

- Nowe statystyki: ulepszenia do SSS, przemiany w Yato, Raito i Yami, użyte bilety i odwrócenia karmy.
- Barter nie zmienia już karmy.

# 1.4.4.43

Data: 2024-03-12
Commit: a4580d9

- Charakter karty wpływa na karmę na wyprawach.
- Strona może filtrować karty po numerach i ma listę kart unikatowych.

# 1.4.4.42

Data: 2024-03-11
Commit: 22c2c5b

- Skorygowano koszt relacji na wyprawach dla niektórych charakterów, m.in. Tsundere.

# 1.4.4.41

Data: 2024-03-11
Commit: ad9e818

- Doświadczenie z wyprawy liczy się przed wydarzeniami, więc przegrana walka poprawnie je zmniejsza.

# 1.4.4.40

Data: 2024-03-11
Commit: 1d81f67

- Lista przedmiotów jest posortowana także według jakości.
- Skorygowano karmę na wyprawie ekstremalnej.

# 1.4.4.39

Data: 2024-03-11
Commit: 5cda6da

- Nowy wygląd kart Gamma i Epsilon, odświeżono też karty Theta i Jota.
- Karty unikatowe mogą mieć własny obrazek.
- Skorygowano koszt relacji na wyprawie dla kart bez obrazka.

# 1.4.4.38

Data: 2024-03-11
Commit: a6f63ba

- Poprawiono statystyki na kartach Beta i Theta.

# 1.4.4.37

Data: 2024-03-10
Commit: 955cb16

- Nowy wygląd kart Beta – każdy charakter ma własną ramkę.

# 1.4.4.36

Data: 2024-03-10
Commit: daac08c

- Poprawiono bonus karmy na zwykłej wyprawie.

# 1.4.4.35

Data: 2024-03-10
Commit: d483633

- Szanse w grze liczą się dokładniej, w procentach.
- Skorygowano progi karmy, przedmioty z wypraw i nagrody za karty ultimate.

# 1.4.4.34

Data: 2024-03-10
Commit: e220468

- Poprawiono źródło przedmiotów na wyprawach jasnych i ciemnych.

# 1.4.4.33

Data: 2024-03-10
Commit: 49a260d

- Skorygowano progi dobrej, złej i neutralnej karmy.
- Wyprawy jasne i ciemne mają nowe zestawy przedmiotów.
- Gracze z neutralną karmą dostają więcej przedmiotów ze zwykłej wyprawy.
- Poprawiono położenie statystyk na kartach.

# 1.4.4.32

Data: 2024-03-08
Commit: 936b5c8

- Nowa rola testera: testerzy mogą używać części poleceń testowych bota.
- Usunięcie karty usuwa też jej zapisane obrazki.
- Skorygowano przedmioty i relację na wyprawach.
- Poprawiono położenie tekstu na profilu.

# 1.4.4.31

Data: 2024-03-08
Commit: d45fd25

- Przebudowano losowanie przedmiotów z wypraw.
- Lista kart na wyprawach pokazuje poprawny czas, po którym karta traci siły.
- Skorygowano karty zwykłych rang na wyprawach.

# 1.4.4.30

Data: 2024-03-04
Commit: 956b5d7

- Nowa ramka awatara: turkusowe liście.

# 1.4.4.29

Data: 2024-03-04
Commit: 8afda6b

- Nowi gracze dostają domyślną ramkę awatara.
- Administracja może zresetować wygląd profilu gracza.

# 1.4.4.28

Data: 2024-03-04
Commit: 89ee908

- Poprawiono wyświetlanie obrazka w stylu profilu.

# 1.4.4.27

Data: 2024-03-03
Commit: 33a2df5

- Obrazek karty i obrazki profilu można wgrać jako załącznik do wiadomości – wystarczy wpisać „att” zamiast linku.

# 1.4.4.26

Data: 2024-03-01
Commit: c1ec1ce

- Przywrócenie domyślnego obrazka karty usuwa zapisany własny obrazek, także na kartach z figurek.

# 1.4.4.25

Data: 2024-03-01
Commit: 3f98501

- Poprawki wewnętrzne.

# 1.4.4.24

Data: 2024-03-01
Commit: 1aab368

- Miejsce w rankingu powyżej tysiąca pokazuje się jako „>1K”.
- Uszkodzony obrazek nie psuje już profilu.

# 1.4.4.23

Data: 2024-02-29
Commit: ec0d2dd

- Wyszukiwanie kart postaci może pokazać tylko karty z własnym obrazkiem i pogrupować je według właściciela.

# 1.4.4.22

Data: 2024-02-29
Commit: bd7049a

- Wyszukiwanie pokazuje w nagłówku, ile kart znaleziono.
- Brakujący obrazek nie psuje już generowania karty.

# 1.4.4.21

Data: 2024-02-29
Commit: c934d5c

- Listy kart i graczy w wyszukiwaniu są ponumerowane, a przy postaciach widać KC i liczbę kart.

# 1.4.4.20

Data: 2024-02-27
Commit: cb7ca7e

- Poprawiono zmianę charakteru przy podanej liczbie przedmiotów.

# 1.4.4.19

Data: 2024-02-27
Commit: 44bb752

- Przy zmianie charakteru karty można podać docelowy charakter – bot użyje tylu przedmiotów, ile trzeba, żeby go wylosować.

# 1.4.4.18

Data: 2024-02-27
Commit: 8b3faf5

- Nagrody z wypraw rosną równo z czasem, bez wolniejszego początku.
- Skorygowano karmę za przedmioty zwiększające relację.
- Charaktery Kamidere i Yandere wpływają na zmianę karmy.

# 1.4.4.17

Data: 2024-02-27
Commit: 39c757d

- Nowe polecenie napraw ramke (fix border): naprawia wygasły obrazek własnej ramki karty.

# 1.4.4.16

Data: 2024-02-27
Commit: 1d6a67f

- Karty ultimate mogą wyruszać na więcej rodzajów wypraw, ale nie zdobywają na nich doświadczenia.
- Skorygowano wymagania karmy dla wypraw jasnych i ciemnych.

# 1.4.4.15

Data: 2024-02-27
Commit: a9985fe

- Skorygowano wyprawy dla kart ultimate i kart o specjalnych charakterach.
- Skorygowano przepisy na krople krwi.

# 1.4.4.14

Data: 2024-02-26
Commit: e5e63b6

- Przebalansowano wyprawy zwykłych kart.
- Naprawiono liczenie dodatkowego ataku na zwykłych kartach.
- Skorygowano koszt druciarstwa.

# 1.4.4.13

Data: 2024-02-26
Commit: 4083abb

- Skorygowano wyprawy oraz karmę za niszczenie i uwalnianie kart.

# 1.4.4.12

Data: 2024-02-25
Commit: 55edd48

- Własne obrazki i ramki kart oraz obrazki profilu zapisują się na serwerze bota, więc nie znikają, gdy wygaśnie link.
- Polecenie naprawiania obrazków obsługuje też linki media.discordapp.net.
- Dokładniejsze komunikaty przy błędnym linku do obrazka.

# 1.4.4.11

Data: 2024-02-24
Commit: 895f447

- Poprawiono rozpoznawanie linków w konfiguracji profilu.

# 1.4.4.10

Data: 2024-02-23
Commit: 74de5cc

- Nowe opcje konfiguracji profilu: ukrywanie nakładki i ultra nakładki.

# 1.4.4.9

Data: 2024-02-22
Commit: fadc05f

- Czytelniejsze listy w pomocy konfiguracji profilu.

# 1.4.4.8

Data: 2024-02-22
Commit: f90aba3

- W konfiguracji profilu opcje, style i ramki można wybierać numerem z listy.
- Nowe skróty polecenia: conprof, conp i konp.

# 1.4.4.7

Data: 2024-02-21
Commit: 09fc94b

- Nowa opcja konfiguracji profilu: ultra nakładka, czyli obrazek nad całym profilem poza paskiem.

# 1.4.4.6

Data: 2024-02-21
Commit: 581dd24

- Nowe opcje konfiguracji profilu: okrągły awatar bez ramki i przeźroczysty pasek.

# 1.4.4.5

Data: 2024-02-21
Commit: 12ac737

- Nowa opcja konfiguracji profilu: ramka na poziom, czyli ramka awatara zmieniająca się wraz z poziomem.

# 1.4.4.4

Data: 2024-02-21
Commit: 96cb4b0

- Nowe ramki awatara: metalowa, kwiatki, czaszka, ogień, lód, promium, złota, czerwona, tęcza, różowa i prosta.

# 1.4.4.3

Data: 2024-02-21
Commit: 67f8e70

- Poprawiono listę postaci z tytułów, żeby się nie powtarzały.

# 1.4.4.2

Data: 2024-02-20
Commit: 5cd341b

- Poprawiono literówki w opisach konfiguracji profilu.

# 1.4.4.1

Data: 2024-02-20
Commit: 2321267

- Nowe polecenie konfiguracja profilu (configure profile, konprof): wszystkie ustawienia profilu w jednym miejscu – tło, styl, nakładka, ramka awatara, przeźroczystość cieni, położenie paska, widoczność paneli i mini galerii oraz liczba kart w mini galerii. Opcja „jestem leniwy” ustawia tło i styl naraz z jednego obrazka.
- Zastąpiło ono osobne polecenia styl, tło, widok profilu, widok waifu i wersja profilu.

# 1.4.4.0

Data: 2024-02-20
Commit: 6807b3f

- Usunięto stary wygląd profilu – zostaje tylko nowy, z paskiem u góry albo na dole.
- Mini galerię na profilu można ukryć.

# 1.4.3.45

Data: 2024-02-19
Commit: 2fe247c

- Profil może mieć własną nakładkę.
- Drobne poprawki.

# 1.4.3.44

Data: 2024-02-19
Commit: 4226b7e

- Poprawiono ustawienia widoku profilu.

# 1.4.3.43

Data: 2024-02-19
Commit: 055817d

- Nowe style profilu: mini galeria i mini galeria na obrazku.
- Widok profilu pozwala zmniejszyć galerię do mniejszej liczby większych kart.
- Nowe ikonki walut w portfelu.

# 1.4.3.42

Data: 2024-02-19
Commit: b6b80e5

- Nowe ikonki na profilu.
- Statystyki kart pokazują, ile kart ma własny obrazek i własną ramkę.

# 1.4.3.41

Data: 2024-02-19
Commit: a8bd75f

- Poprawiono jeden ze stylów profilu.
- Polecenie odcinki działa poza kanałem poleceń dla graczy z odpowiednim poziomem lub rolą nitro.

# 1.4.3.40

Data: 2024-02-19
Commit: 7ebda55

- Poprawiono profil bota i miejsce w rankingu.

# 1.4.3.39

Data: 2024-02-19
Commit: fb38368

- Karty spoza ustawionej kolejności galerii pojawiają się na profilu po tych ustawionych.

# 1.4.3.38

Data: 2024-02-19
Commit: 334a4bf

- Poprawiono zamianę paneli i kolejność galerii na profilu.

# 1.4.3.37

Data: 2024-02-19
Commit: 207045b

- Widok profilu pozwala zamienić panele miejscami.

# 1.4.3.36

Data: 2024-02-19
Commit: 25b5ee2

- Nowy wygląd statystyk kart na profilu: liczba kart według rangi, limit kart i znaczek karmy.
- Nowa ramka awatara.

# 1.4.3.35

Data: 2024-02-18
Commit: 93021c9

- Nowe polecenie widok profilu (profile view): włącza i wyłącza statystyki anime, mangi i kart na profilu.

# 1.4.3.34

Data: 2024-02-18
Commit: 63dae60

- Nowe style profilu: galeria na obrazku i statystyki na obrazku.
- Nowe ramki awatara.

# 1.4.3.33

Data: 2024-02-18
Commit: 578a7c5

- Nowy profil ma ramki awatara.

# 1.4.3.32

Data: 2024-02-18
Commit: d6e93cf

- Portfel pokazuje waluty z ikonkami.

# 1.4.3.31

Data: 2024-02-18
Commit: 51aba0b

- Nowe polecenie wersja profilu (profile version): stary profil, nowy z paskiem u góry albo nowy z paskiem na dole.

# 1.4.3.30

Data: 2024-02-18
Commit: 8fabe64

- Tło i obrazek profilu zapisują się w rozmiarze pasującym do nowego profilu.

# 1.4.3.29

Data: 2024-02-18
Commit: 9132801

- Poprawiono profil Sanakan.

# 1.4.3.28

Data: 2024-02-18
Commit: f72a115

- Poprawiono wyświetlanie profilu.

# 1.4.3.27

Data: 2024-02-18
Commit: b3f6415

- Galeria na profilu pokazuje karty w kolejności ustawionej poleceniem sortowanie galerii.

# 1.4.3.25

Data: 2024-02-18
Commit: 2e63518

- Nowy wygląd profilu: pasek z poziomem, miejscem w rankingu i walutami, większe tło i galeria kart.

# 1.4.3.24

Data: 2024-02-17
Commit: 72b64e3

- Polecenie wytwórz może wytworzyć kilka przedmiotów naraz.

# 1.4.3.23

Data: 2024-02-16
Commit: 878a18b

- Skorygowano koszt druciarstwa.

# 1.4.3.22

Data: 2024-02-15
Commit: 12c0220

- Skorygowano koszt druciarstwa.

# 1.4.3.21

Data: 2024-02-15
Commit: a91ea9a

- Wyprawy działają szybciej: nagrody i koszty naliczają się w krótszym czasie.
- Naprawiono sprawdzanie limitu statystyk karty.

# 1.4.3.20

Data: 2024-02-15
Commit: 11d28ec

- Zmieniono działanie kropli krwi waifu na kartach o różnych charakterach i dodano limit wzmacniania siły.
- Kroplę krwi waifu można kupić w sklepie.
- Skorygowano przepisy, szanse na przedmioty i karmę za niszczenie kart.

# 1.4.3.19

Data: 2024-02-15
Commit: aa38d1a

- Przemiana karty w demona wymaga teraz kropli krwi twojej waifu, a w anioła – kropli twojej krwi.

# 1.4.3.18

Data: 2024-02-15
Commit: 2a82acb

- Do ulepszenia skrzyni doświadczenia można użyć obu rodzajów krwi.

# 1.4.3.17

Data: 2024-02-15
Commit: da005e0

- Skorygowano przepisy i szanse na zdobycie kropli krwi.

# 1.4.3.16

Data: 2024-02-14
Commit: be3e781

- Nowe polecenia wytwórz (craft) i przepisy (recipes): wytwarzanie przedmiotów według przepisów. Zastąpiły polecenie wymień na kule.

# 1.4.3.15

Data: 2024-02-14
Commit: 6dd05ee

- Gdy Shinden nie odpowiada, sezonowy pakiet z loterii zamienia się w zwykły duży pakiet zamiast błędu.

# 1.4.3.14

Data: 2024-02-14
Commit: a6cddc2

- Skorygowano wpływ niszczenia i uwalniania kart oraz niektórych przedmiotów na karmę.
- Karta utracona na wyprawie może czasem zostawić nieśmiertelnik.

# 1.4.3.13

Data: 2024-02-13
Commit: 5bd4129

- Nowe przedmioty: nieśmiertelnik (zwiększa limit własnych oznaczeń), krwawa mary (zdejmuje klątwę z karty) i rozbita butelka (potrzebna do wytwarzania przedmiotów).
- Nieśmiertelnik można wygrać na loterii; zastąpił polecenie wykup oznaczenie.
- Kilka kropli krwi można zużyć naraz.
- Polecenie naprawiania wygasłych obrazków kart obsługuje też obrazki z Google Drive.

# 1.4.3.12

Data: 2024-02-13
Commit: 814261d

- Poprawiono oznaczanie kart otrzymanych w wymianie.

# 1.4.3.11

Data: 2024-02-11
Commit: bbc07e5

- Karta oznaczona jako ulubiona nie zostanie zniszczona po wyprawie, nawet jeśli ma też oznaczenie kosz.
- Przeklęte karty mają ikonkę 💀.

# 1.4.3.10

Data: 2024-02-11
Commit: 59c1e41

- Nowe polecenie wyprawa na koniec (expedition on end): ustawia, czy karta z oznaczeniem kosz ma być po powrocie z wyprawy zniszczona, uwolniona czy zostawiona.
- Polecenia zniszcz i uwolnij bez podanych kart działają na karty z oznaczeniem kosz.
- Nowe polecenie sortowanie galerii (sort gallery): ustala kolejność kart w galerii na stronie.
- Czytelniejsza lista wydarzeń z wyprawy.

# 1.4.3.9

Data: 2024-02-10
Commit: b8d22f4

- Stare oznaczenia można przywrócić także do oznaczeń domyślnych (np. ulubione).
- Odświeżono grafikę kart Delta.

# 1.4.3.8

Data: 2024-02-10
Commit: 62cf2a7

- Dopisanie „|tldr” na końcu oznaczenia przy wyszukiwaniu i listach wysyła wynik jako plik tekstowy na PW.

# 1.4.3.7

Data: 2024-02-10
Commit: f4e5a5c

- Skorygowano liczbę darmowych oznaczeń i cenę dodatkowych.
- Dokładniejsze komunikaty przy przywracaniu oznaczeń.

# 1.4.3.6

Data: 2024-02-10
Commit: b2458a4

- Naprawiono zapisywanie nowo utworzonego oznaczenia.

# 1.4.3.5

Data: 2024-02-10
Commit: b3a2f1a

- Poprawki przywracania oznaczeń.

# 1.4.3.4

Data: 2024-02-10
Commit: 6176bab

- Przywracanie oznaczenia pozwala wybrać nową nazwę.

# 1.4.3.3

Data: 2024-02-09
Commit: e33fd16

- Nowe polecenie wykup oznaczenie (buy tag): dokupuje miejsce na kolejne własne oznaczenia.
- Nowe polecenie przywróć oznaczenie (restore tag): przenosi karty ze starych oznaczeń do nowych.

# 1.4.3.2

Data: 2024-02-09
Commit: 9c72d4a

- Nowe polecenie utwórz oznaczenie (create tag): tworzy własne oznaczenie.
- Nowe polecenie modyfikuj oznaczenie (modify tag): zmienia nazwę własnego oznaczenia albo je usuwa.

# 1.4.3.1

Data: 2024-02-09
Commit: c76d2f3

- Nowe polecenie moje oznaczenia (my tags): lista twoich i domyślnych oznaczeń z liczbą kart.

# 1.4.3.0

Data: 2024-02-09
Commit: 0c41823

- Nowe oznaczenia kart: domyślne oznaczenia mają ikonki – ulubione 💗, galeria 📌, rezerwacja 📝, wymiana 🔄 i kosz 🗑️.
- Czytelniejsze komunikaty przy oznaczaniu kart.
- Usunięto statystyki areny.

# 1.4.2.15

Data: 2024-02-07
Commit: 9119163

- Skorygowano wpływ bartera na karmę.
- Kolejna poprawka nagród z wypraw.

# 1.4.2.14

Data: 2024-02-07
Commit: 1b965bb

- Naprawiono błąd w wyliczaniu nagród z wypraw.

# 1.4.2.13

Data: 2024-02-07
Commit: 0f2e6f7

- Opis karty pokazuje, kiedy ustawiono jej własny obrazek i czy jest animowany.
- Poprawiono informacje o kartach i postaciach pobierane z Shindena.

# 1.4.2.12

Data: 2024-02-07
Commit: b98772d

- Ukończona figurka pokazuje datę ukończenia i numer karty, która z niej powstała.
- Czytelniejsze komunikaty bartera i niszczenia kart.

# 1.4.2.11

Data: 2024-02-07
Commit: d4dc638

- Gracze z rolą nitro mogą używać części poleceń (np. darmowej karty) także poza kanałem poleceń, a kolory nicku dostają za darmo.
- Administracja może ustawić rolę nitro poleceniem nitror.

# 1.4.2.10

Data: 2024-02-07
Commit: 1f7a91c

- Figurka zapamiętuje kartę, która z niej powstała.
- Poprawiono wygląd statystyk z Shindena.

# 1.4.2.9

Data: 2024-02-06
Commit: 2d57b7f

- Barter nie wymienia już cennych przedmiotów (np. kamery, biletów, szkieletów figurek), chyba że poprzedzisz numer przedmiotu znakiem „!”.
- Gdy masz mniej przedmiotów niż podano, barter wymienia tyle, ile masz, i pisze, czego nie udało się wymienić.
- Losowanie w grach jest bardziej sprawiedliwe.
- Rzut monetą pokazuje obrazek w samej wiadomości.
- Automat liczy też wygrane w pionowych kolumnach.

# 1.4.2.8

Data: 2024-02-06
Commit: 92aa9ea

- Administracja może odebrać graczowi przedmioty.

# 1.4.2.7

Data: 2024-02-04
Commit: 3a68c70

- Statystyki pokazują osobno AC wydane na pakiety i na przedmioty.

# 1.4.2.6

Data: 2024-02-04
Commit: 4b9eecc

- Bot nie próbuje już wysyłać zbyt dużych animowanych obrazków kart – zamiast tego podaje link.
- Statystyki dokładniej pokazują, na co wydano TC.
- Komunikat przy rozbudowie figurki podaje, ile punktów dały wszystkie użyte przedmioty.

# 1.4.2.5

Data: 2024-02-03
Commit: 3241b5c

- Nowe polecenie chce wszystkie waifu (i want them all): rozszerza pulę postaci w pakietach i darmowych kartach o postacie z mang.

# 1.4.2.4

Data: 2024-02-03
Commit: 95c901e

- Nowy przedmiot: kamera, która pozwala ustawić karcie własny animowany obrazek. Można ją kupić w sklepie.
- Naprawiono sprawdzanie rodzaju obrazka przy kartach z figurek.

# 1.4.2.3

Data: 2024-02-03
Commit: bc48d84

- Dalsze korekty balansu wypraw, karmy i kropli krwi.

# 1.4.2.2

Data: 2024-02-03
Commit: aa57d5c

- Nowy przedmiot: kropla krwi twojej waifu. Na kartach o różnych charakterach działa inaczej – może zwiększyć siłę, liczbę ulepszeń albo spaczyć kartę.
- Skorygowano koszt relacji, liczbę przedmiotów i doświadczenie na wyprawach.
- Skorygowano wpływ na karmę i szanse na przedmioty.
- Skorygowano cenę zmiany karty na wymienialną; gracze z dobrą karmą płacą mniej.

# 1.4.2.1

Data: 2024-02-02
Commit: 1ea760b

- Nowe polecenie lazyc (lc): darmowa karta w trybie leniwym, która od razu niszczy niechcianą kartę albo ją oznacza.
- Zwykła darmowa karta też może niszczyć, uwalniać i oznaczać kartę.

# 1.4.2.0

Data: 2024-02-02
Commit: 038e4c0

- Polecenia różnych graczy wykonują się teraz równolegle, więc jeden gracz nie blokuje pozostałych.

# 1.4.1.5

Data: 2024-02-02
Commit: 54f99c7

- Polecenie napraw tytuł może wymusić dokładnie podany tytuł.

# 1.4.1.4

Data: 2024-02-02
Commit: 1cbd41a

- Karty z druciarstwa mają jako źródło wpisane „Druciarstwo”.

# 1.4.1.3

Data: 2024-02-02
Commit: 9b6b8ad

- Nowe polecenie lazyt (lt): druciarstwo w trybie leniwym, które od razu niszczy niechciane karty i oznacza resztę.
- Zwykłe druciarstwo też może niszczyć, uwalniać i oznaczać tworzone karty.
- Skorygowano domyślne niszczenie kart w poleceniu lazyp.

# 1.4.1.2

Data: 2024-02-01
Commit: 8fa5c43

- Druciarstwo sprawdza, czy masz miejsce na nowe karty.
- Barter wpływa na karmę.

# 1.4.1.1

Data: 2024-02-01
Commit: 8d1ec1e

- Tworzenie kart z przedmiotów zastąpiło druciarstwo: polecenie barter (scrape) zamienia przedmioty na fragmenty kart, a polecenie druciarstwo (tinkering) tworzy z fragmentów nowe karty.

# 1.4.1.0

Data: 2024-01-31
Commit: 5777f12

- Nowe polecenie napraw tytuł (fix title): zmienia tytuł serii na twojej karcie na inny, z którym postać jest powiązana.
- Do listy życzeń nie można już dodawać pojedynczych kart, tylko postacie i tytuły.
- Wymiana nie zacznie się, gdy któryś z graczy jest na czarnej liście albo nie ma już miejsca na karty.
- Galeria na stronie pokazuje karty animowane przed zwykłymi.
- Obrazki profilu mają tło w nowym kolorze Discorda.
- Wyprawa z błędnie policzonym czasem nie kończy się, zamiast dawać złe nagrody.

# 1.3.28.1

Data: 2024-01-20
Commit: 7917a2c

- Poprawiono wyświetlanie dużych obrazków kart.

# 1.3.28.0

Data: 2024-01-20
Commit: 56687b8

- Karty mogą mieć animowane obrazki.

# 1.3.27.40

Data: 2023-12-30
Commit: 281344f

- Aktywności oznaczają tylko kartę.
- Do aktywności trafiają tylko karty, których chce więcej niż jedna osoba.

# 1.3.27.39

Data: 2023-12-18
Commit: 606e342

- Wygasły obrazek karty można naprawić dwa razy.

# 1.3.27.38

Data: 2023-12-10
Commit: 82ddc9d

- Pakiet z tytułu wymaga tytułu z większą liczbą postaci.

# 1.3.27.37

Data: 2023-12-09
Commit: 7b434fc

- Poprawiono sprawdzanie obrazka w profilu.

# 1.3.27.36

Data: 2023-12-05
Commit: 29a4199

- Strona nie pokazuje kart ani profilu osób z czarnej listy.

# 1.3.27.35

Data: 2023-12-04
Commit: f6a0a46

- Poprawiono kończenie się kolorów i rangi globalnych emotek.

# 1.3.27.34

Data: 2023-12-04
Commit: 5860636

- Kończenie się kolorów i rangi globalnych emotek jest sprawdzane wydajniej.

# 1.3.27.33

Data: 2023-12-04
Commit: 5539506

- Poprawiono opis karty w aktywnościach.

# 1.3.27.32

Data: 2023-12-04
Commit: c5affde

- Zdobycie karty, której chce wiele osób, trafia do aktywności jako osobny rodzaj.

# 1.3.27.31

Data: 2023-12-03
Commit: c4f16fa

- Poprawki wewnętrzne.

# 1.3.27.30

Data: 2023-12-02
Commit: ea84bf5

- Administracja może przerwać trwającą serię loterii kart.

# 1.3.27.29

Data: 2023-12-02
Commit: c6657ce

- Poprawki wewnętrzne.

# 1.3.27.28

Data: 2023-12-02
Commit: 325e3c4

- Poprawki wewnętrzne.

# 1.3.27.27

Data: 2023-12-01
Commit: c2523ad

- Polecenie talia działa szybciej.
- Bot zbiera mniej danych o użyciu poleceń.

# 1.3.27.26

Data: 2023-11-29
Commit: 8b0db20

- Strona może sortować karty po tym, czy są wymienialne.
- Nieistniejące karty nie dostają linku.

# 1.3.27.25

Data: 2023-11-28
Commit: 19df17f

- Wiele poleceń działa szybciej, bo nie wczytuje całej kolekcji kart.

# 1.3.27.24

Data: 2023-11-27
Commit: b952b53

- Listę życzeń można dostać jako plik tekstowy, a bardzo długa przychodzi tak sama.

# 1.3.27.23

Data: 2023-11-27
Commit: 0be1e4a

- Rankingi i informacje z Shindena ładują się szybciej.

# 1.3.27.22

Data: 2023-11-26
Commit: 92d167a

- Wiadomości bota mogą być dłuższe.

# 1.3.27.21

Data: 2023-11-26
Commit: b63bcf4

- Karty wygrane w loterii trafiają do aktywności.
- Więcej numerów kart prowadzi do strony karty.

# 1.3.27.20

Data: 2023-11-24
Commit: ece9cfb

- Poprawiono aktywność za wygraną w loterii.

# 1.3.27.19

Data: 2023-11-24
Commit: 186fc5b

- Strona może pobierać tylko nowe aktywności.

# 1.3.27.18

Data: 2023-11-23
Commit: 2af7c77

- Ban zostaje zapisany w aktywnościach i ogłoszony na kanale powiadomień.

# 1.3.27.17

Data: 2023-11-16
Commit: e6b9717

- Strona szybciej pokazuje nazwy graczy.

# 1.3.27.16

Data: 2023-11-15
Commit: 48e0ab6

- Poprawiono linki udostępniania z Dysku Google.

# 1.3.27.15

Data: 2023-11-15
Commit: c7ede20

- Poprawiono obrazki z Dysku Google.

# 1.3.27.14

Data: 2023-11-15
Commit: 040f188

- Własne obrazki można dodawać z Dysku Google.

# 1.3.27.13

Data: 2023-11-15
Commit: 00ccc2f

- Numery kart w wiadomościach prowadzą do strony karty.

# 1.3.27.12

Data: 2023-11-15
Commit: 6748aac

- Poprawiono sprawdzanie obrazków.

# 1.3.27.11

Data: 2023-11-14
Commit: 899831e

- Linki z Dropboxa są same zamieniane na bezpośrednie.

# 1.3.27.10

Data: 2023-11-14
Commit: bf58723

- Przywrócono obrazki z imgura.

# 1.3.27.9

Data: 2023-11-14
Commit: 9d0b844

- Własne obrazki można dodawać z OneDrive.
- Bot sprawdza, czy podany adres naprawdę prowadzi do obrazka.
- Naprawianie obrazków znowu działa.

# 1.3.27.8

Data: 2023-11-14
Commit: dcf20b0

- Poprawiono oznaczanie bota w wynikach wyszukiwania.

# 1.3.27.7

Data: 2023-11-14
Commit: d1a66d6

- Aktywności zapisują więcej szczegółów, na przykład nazwę gracza i dane karty.

# 1.3.27.6

Data: 2023-11-14
Commit: 850a642

- Opis karty na stronie zawiera nazwę właściciela.

# 1.3.27.5

Data: 2023-11-13
Commit: a054baa

- Strona może pobrać opis pojedynczej karty.
- Naprawianie obrazków obsługuje kilka serwisów i można je wyłączyć.

# 1.3.27.4

Data: 2023-11-10
Commit: 3d5408e

- Przywrócono poprzedni sposób wysyłania długich list.

# 1.3.27.3

Data: 2023-11-10
Commit: 5be7d9e

- Aktywności pokazują karty w nowy sposób.

# 1.3.27.2

Data: 2023-11-10
Commit: 9f67640

- Zmieniono wysyłanie długich list w prywatnych wiadomościach.

# 1.3.27.1

Data: 2023-11-10
Commit: 07125ab

- Poprawiono zapisywanie aktywności za nowy poziom.

# 1.3.27.0

Data: 2023-11-10
Commit: 2e58eff

- Bot zapisuje aktywności graczy, na przykład zdobycie karty SSS lub nowego poziomu, i udostępnia je stronie.

# 1.3.26.52

Data: 2023-10-23
Commit: 4b72658

- Skorygowano ceny w sklepach.

# 1.3.26.51

Data: 2023-10-23
Commit: 7c58b30

- Otwieranie kilku pakietów naraz też sprawdza listy życzeń.
- Skorygowano ceny w sklepach.

# 1.3.26.50

Data: 2023-10-23
Commit: e1c1da7

- Nowe rodzaje powiadomień o odcinkach.

# 1.3.26.49

Data: 2023-08-28
Commit: 544fc05

- Nadzór surowiej liczy linki od osób bez podstawowej roli.

# 1.3.26.48

Data: 2023-08-23
Commit: 57566e2

- Nadzór nie karze za linki do zaufanych stron.

# 1.3.26.47

Data: 2023-08-22
Commit: f5aca0a

- Polecenie kto chce zeruje licznik, gdy nikt nie chce karty.

# 1.3.26.46

Data: 2023-08-22
Commit: 84d776a

- Poprawiono liczenie chętnych w poleceniu kto chce.

# 1.3.26.45

Data: 2023-08-21
Commit: da31b25

- Osoby bez podstawowej roli nie zbierają postępu do pakietów za aktywność.

# 1.3.26.44

Data: 2023-07-21
Commit: bb5a1d6

- Poprawki wewnętrzne.

# 1.3.26.43

Data: 2023-07-21
Commit: 3038bad

- Poprawki wewnętrzne.

# 1.3.26.42

Data: 2023-06-27
Commit: 3b1a8f8

- Bot pokazuje nowe, globalne nazwy użytkowników Discorda.

# 1.3.26.41

Data: 2023-06-25
Commit: 9241b8b

- Poprawiono używanie plasteliny.

# 1.3.26.40

Data: 2023-06-18
Commit: 3c35224

- Moderatorzy mogą używać kolejnego polecenia.

# 1.3.26.39

Data: 2023-06-13
Commit: f0e1a41

- Poprawki wewnętrzne.

# 1.3.26.38

Data: 2023-05-22
Commit: b404e40

- Poprawiono pakiety sezonowe.

# 1.3.26.37

Data: 2023-05-09
Commit: a81930d

- Safari, loteria kart, karta+ i tworzenie kart pokazują zielone serce dla kart z twojej listy życzeń.

# 1.3.26.36

Data: 2023-04-30
Commit: fee0fbc

- Nowe polecenie napraw obrazek (fix image): pozwala raz podmienić wygasły własny obrazek karty.

# 1.3.26.35

Data: 2023-04-30
Commit: 9489a16

- Serwer może dodać własną podpowiedź dla osób bez podstawowej roli.

# 1.3.26.34

Data: 2023-04-30
Commit: 4cb2e69

- Własne obrazki mogą pochodzić tylko z zaufanych serwisów.

# 1.3.26.33

Data: 2023-04-26
Commit: c63a667

- Poprawki narzędzi administracji.

# 1.3.26.32

Data: 2023-04-26
Commit: bc8593c

- Obrazki używane przez bota są na własnym serwerze.

# 1.3.26.31

Data: 2023-04-18
Commit: ca8e6b6

- Linki w wiadomościach bota nie są pogrubione.

# 1.3.26.30

Data: 2023-04-15
Commit: 964d262

- Ostrzeżenie o możliwym multikoncie oznacza konta.

# 1.3.26.29

Data: 2023-04-15
Commit: 6c85f3d

- Administracja może stworzyć kartę od razu z jakością ultimate.

# 1.3.26.28

Data: 2023-04-14
Commit: 60fb8ff

- Pomoc wypisuje polecenia alfabetycznie.

# 1.3.26.27

Data: 2023-04-11
Commit: 92c74d8

- Poprawiono podawanie liczby przedmiotów przy tworzeniu kart.

# 1.3.26.26

Data: 2023-04-10
Commit: 8cbab65

- Zmieniono wiadomość o wygranej w loterii kart.

# 1.3.26.25

Data: 2023-04-09
Commit: 7ec9b77

- Poprawiono zużywanie przedmiotów.

# 1.3.26.24

Data: 2023-04-08
Commit: 3c8ad02

- Poprawiono plastelinę: listę obrazków wywołuje się słowem lista.

# 1.3.26.23

Data: 2023-04-06
Commit: 0eb5876

- Tworzenie kart pokazuje, ile osób chce nowej karty.

# 1.3.26.22

Data: 2023-04-05
Commit: f8919ec

- Loteria kart pokazuje, ile osób chce wygranych kart.

# 1.3.26.21

Data: 2023-04-02
Commit: 99ea8ff

- Administracja może sprawdzić czas serwera.

# 1.3.26.20

Data: 2023-03-27
Commit: ec44301

- Safari pokazuje, ile osób chce danej karty.

# 1.3.26.19

Data: 2023-03-26
Commit: 5f22bb6

- Pakiety sezonowe z loterii same pobierają z Shindena aktualny sezon anime.

# 1.3.26.18

Data: 2023-03-25
Commit: ad035b1

- Poprawiono przykłady w opisach poleceń.

# 1.3.26.17

Data: 2023-03-25
Commit: 1124a25

- Zwykła lista życzeń też może pomijać tytuły.

# 1.3.26.16

Data: 2023-03-25
Commit: f9a0fa0

- Poprawiono liczenie wartości rynkowej przy wymianie.

# 1.3.26.15

Data: 2023-03-24
Commit: 55d400b

- Lista życzeń z filtrem może pomijać tytuły i ma nowe skróty.
- Statystyki liczą zużyte przepustki.
- Poprawiono opisy poleceń w pomocy.

# 1.3.26.14

Data: 2023-03-18
Commit: 2699444

- Poprawiono opisy poleceń w pomocy.
- Losowanie szans działa dokładniej.

# 1.3.26.13

Data: 2023-03-18
Commit: eb44d61

- Przebudowano pomoc i informacje o użytkowniku oraz serwerze.

# 1.3.26.12

Data: 2023-03-17
Commit: 3b5d94a

- Tryb chaosu ma jeszcze więcej emotek.

# 1.3.26.11

Data: 2023-03-17
Commit: 9b2ae2d

- Tryb chaosu ma więcej emotek i nie reaguje na polecenia.

# 1.3.26.10

Data: 2023-03-16
Commit: 7bec652

- Tryb chaosu zamiast zamieniać nicki dodaje losowe reakcje do wiadomości.

# 1.3.26.9

Data: 2023-03-16
Commit: d41d695

- Poprawiono walki na wyprawach.

# 1.3.26.8

Data: 2023-03-15
Commit: a9e94c0

- Przebudowano listę życzeń i wysyłanie prywatnych wiadomości.

# 1.3.26.7

Data: 2023-03-15
Commit: d42d813

- Listę przedmiotów można przefiltrować po nazwie lub jakości.

# 1.3.26.6

Data: 2023-03-15
Commit: ccf06dd

- Poprawki wewnętrzne.

# 1.3.26.5

Data: 2023-03-14
Commit: 264b49f

- Wyszukiwanie właścicieli postaci działa szybciej.

# 1.3.26.4

Data: 2023-03-13
Commit: da5a577

- Styl gwiazdek można podać bez polskich znaków.
- Skorygowano próg wysokiej wartości rynkowej.

# 1.3.26.3

Data: 2023-03-12
Commit: 3fc7e0a

- Poprawiono podawanie liczby przedmiotów.

# 1.3.26.2

Data: 2023-03-12
Commit: 59632b8

- Poprawiono przedmioty dodające doświadczenie i ustawianie własnego obrazka.

# 1.3.26.1

Data: 2023-03-12
Commit: a154ad1

- Poprawiono przykład w opisie polecenia.

# 1.3.26.0

Data: 2023-03-12
Commit: 23e9632

- Nowe polecenie użyjbk (usewc): używa przedmiotu bez wskazywania karty.
- Przebudowano używanie przedmiotów.

# 1.3.25.22

Data: 2023-03-11
Commit: 7d01113

- Poprawiono polecenia w trakcie wymiany.

# 1.3.25.21

Data: 2023-03-11
Commit: bfa2f41

- Poprawiono tworzenie nowych kart.

# 1.3.25.20

Data: 2023-03-11
Commit: aaaccea

- Polecenie wymiana pozwala oznaczyć otrzymane karty tagiem.

# 1.3.25.19

Data: 2023-03-11
Commit: 13e3eda

- Poprawki wewnętrzne.

# 1.3.25.18

Data: 2023-03-11
Commit: 30a3c53

- Uwalnianie i niszczenie kart podaje, ile kart pominięto.

# 1.3.25.17

Data: 2023-03-08
Commit: b964715

- Pełny opis karty pokazuje charakter.

# 1.3.25.16

Data: 2023-03-08
Commit: 0b5d02d

- Opis karty pokazuje jej moc.

# 1.3.25.15

Data: 2023-03-08
Commit: ef36500

- Aktualizacja karty przelicza jej moc.

# 1.3.25.14

Data: 2023-03-07
Commit: 8d8ee14

- Nowe stopnie skrajnej relacji z kartą.
- CT za zniszczenie karty zależy od jej wartości rynkowej.

# 1.3.25.13

Data: 2023-03-07
Commit: 2e77a5b

- Zmieniono liczenie wartości rynkowej przy wymianie.
- Uwalnianie i niszczenie kart daje stałą karmę.

# 1.3.25.12

Data: 2023-03-07
Commit: 76f285d

- Poprawiono wynik polecenia kto chce anime.

# 1.3.25.11

Data: 2023-03-05
Commit: 7095037

- Długa lista osób chcących karty przychodzi w prywatnej wiadomości.
- Poprawiono liczenie chętnych.

# 1.3.25.10

Data: 2023-03-05
Commit: 1fe248d

- Polecenie lazyp oznacza karty z twojej listy życzeń osobnym tagiem.
- Karty o charakterach Raito, Yami i Yato są tańsze w utrzymaniu na wyprawie.

# 1.3.25.9

Data: 2023-03-05
Commit: 1618f78

- Polecenie kto chce aktualizuje liczbę chętnych na wszystkich kartach postaci.

# 1.3.25.8

Data: 2023-03-04
Commit: feddd3c

- Pakiety ze strony nie liczą się do misji.

# 1.3.25.7

Data: 2023-03-04
Commit: 52da1ad

- Poprawiono nazwy postaci w liczniku chętnych.

# 1.3.25.6

Data: 2023-03-04
Commit: 7c7539e

- Opis karty i polecenie kto chce pokazują liczbę chętnych.

# 1.3.25.5

Data: 2023-03-04
Commit: b05f21f

- Szkielet można zużyć na punkty konstrukcji figurki.

# 1.3.25.4

Data: 2023-03-02
Commit: a1399c3

- Polecenie loteria ma skrót dej i działa poza kanałami poleceń od określonego poziomu.

# 1.3.25.3

Data: 2023-03-02
Commit: d9b7a8d

- Poprawiono nazwę pakietu z loterii.

# 1.3.25.2

Data: 2023-03-02
Commit: e41cc6c

- Karty z loterii mają własne źródło.
- Skorygowano nagrody z loterii.

# 1.3.25.1

Data: 2023-03-02
Commit: 3cadbb0

- Pakiety z loterii mają nazwy zależne od rodzaju.
- Skorygowano liczbę części figurek z loterii.

# 1.3.25.0

Data: 2023-03-02
Commit: 3ea320f

- Nowe polecenie loteria (lottery): za przepustkę losuje nagrodę, na przykład pakiet, przedmioty lub doświadczenie.

# 1.3.24.5

Data: 2023-03-01
Commit: b21ff0e

- Strona może przeglądać wszystkie karty w grze.

# 1.3.24.4

Data: 2023-02-28
Commit: 57308b6

- Nowe polecenie galerianka (premium waifu): za TC ustawia wybraną kartę jako waifu.

# 1.3.24.3

Data: 2023-02-28
Commit: 4b2775a

- Karta waifu jest wybierana tak samo we wszystkich miejscach.

# 1.3.24.2

Data: 2023-02-26
Commit: 1d11f32

- Karty ultimate skracają czas oczekiwania na kartę+.

# 1.3.24.1

Data: 2023-02-26
Commit: 485dc85

- Zmieniono szanse na przedmioty z wypraw.
- Poprawiono profil.

# 1.3.24.0

Data: 2023-02-25
Commit: e51d85e

- Poprawki wewnętrzne.

# 1.3.23.11

Data: 2023-02-22
Commit: 907fd11

- Pigułki działają mocniej.
- Bonus do życia z jedzenia ma górny limit.

# 1.3.23.10

Data: 2023-02-17
Commit: ed1e796

- Wyszukiwanie postaci może pokazać tytuł, z którego pochodzi postać.

# 1.3.23.9

Data: 2023-02-15
Commit: 0d1d413

- Poprawiono linki do Shindena na liście życzeń.

# 1.3.23.8

Data: 2023-02-11
Commit: 67615d7

- Strona może pobrać listę najbardziej chcianych postaci.

# 1.3.23.7

Data: 2023-02-08
Commit: 19b3c5f

- Komunikat o kanale lub poziomie potrzebnym do polecenia ma obrazek.

# 1.3.23.6

Data: 2023-02-04
Commit: 91286f8

- Polecenie lazyp domyślnie niszczy mniej kart.

# 1.3.23.5

Data: 2023-02-01
Commit: 58208ae

- Wynik serii loterii kart pokazuje postęp.

# 1.3.23.4

Data: 2023-01-28
Commit: 5d05568

- Zmieniono sprawdzanie, czy gracz jest wyciszony, przy safari i loterii kart.

# 1.3.23.3

Data: 2023-01-11
Commit: af00542

- Nowe polecenie dla moderacji kasujs (prunes): usuwa wskazaną wiadomość.

# 1.3.23.2

Data: 2023-01-11
Commit: 3280bb6

- Informacja o karze pokazuje dodatkowy czas wyciszenia.

# 1.3.23.1

Data: 2023-01-09
Commit: d24a7ac

- Nowe polecenie dla administracji mute modifier: ustawia dodatek do czasu wyciszenia.

# 1.3.23.0

Data: 2023-01-09
Commit: 673b6af

- Administracja może ustawić graczowi stały lub rosnący dodatek do czasu wyciszenia.
- Nowe obrazki przy wyciszeniu.

# 1.3.22.9

Data: 2022-12-30
Commit: 44495d5

- Poprawki wewnętrzne.

# 1.3.22.8

Data: 2022-12-29
Commit: d4a762e

- Profil używa awatara z serwera.

# 1.3.22.7

Data: 2022-12-12
Commit: 85d07de

- Powiadomienia na kanałach ogłoszeniowych są od razu publikowane dalej.

# 1.3.22.6

Data: 2022-12-11
Commit: 6b783f5

- Zmieniono sposób pokazywania właściciela figurki.

# 1.3.22.5

Data: 2022-12-10
Commit: a0bd57a

- Polecenie figurka pokazuje figurki innych graczy i ich właściciela.

# 1.3.22.4

Data: 2022-12-03
Commit: 33e5aa6

- Poprawki wewnętrzne.

# 1.3.22.3

Data: 2022-12-02
Commit: bd4cff9

- Nowa rola póładministratora z dostępem do części poleceń administracji.
- Nowe obrazki przy wyciszeniu.

# 1.3.22.2

Data: 2022-11-29
Commit: 6ceaee0

- Komunikaty o banie i wyciszeniu mają losowy obrazek.

# 1.3.22.1

Data: 2022-11-25
Commit: 3a948e0

- Bot ponawia nieudane połączenia z bazą danych.
- Poprawiono tworzenie kart.

# 1.3.22.0

Data: 2022-11-11
Commit: eaf1f64

- Karta ultimate po wymianie staje się niewymienialna.
- Zmieniono adres repozytorium bota.

# 1.3.21.9

Data: 2022-10-27
Commit: 9b4536a

- Nowe karty ultimate są niewymienialne, a odblokowanie ich jest droższe.

# 1.3.21.8

Data: 2022-09-28
Commit: 42273e5

- Skorygowano doświadczenie trafiające do skrzyni.

# 1.3.21.7

Data: 2022-09-23
Commit: 3747811

- Poprawki wewnętrzne.

# 1.3.21.6

Data: 2022-09-01
Commit: 0db3e90

- Poprawiono zgłaszanie i todo bez odpowiedzi.

# 1.3.21.5

Data: 2022-09-01
Commit: 20637a4

- Wiadomość można zgłosić, odpowiadając na nią poleceniem zgłoś.

# 1.3.21.4

Data: 2022-09-01
Commit: 148737d

- Polecenie todo działa jako odpowiedź na wiadomość.

# 1.3.21.3

Data: 2022-08-30
Commit: 1c33b26

- Wskrzeszanie i gwiazdki dają większy bonus do statystyk.
- Wzmocniono karty ultimate.

# 1.3.21.2

Data: 2022-08-25
Commit: b916ede

- Polecenie karta obrazek domyślnie ukrywa statystyki.
- Skorygowano przedmioty z wypraw kart ultimate.

# 1.3.21.1

Data: 2022-08-23
Commit: 732586f

- Pigułek można używać, a statystyki kart ultimate mają górny limit.
- Czarną pigułkę można kupić w sklepie.

# 1.3.21.0

Data: 2022-08-23
Commit: 25f171d

- Nowe przedmioty: pigułki, które zwiększają statystyki kart ultimate.
- Najtrudniejsze wyprawy kart ultimate mają własne nagrody.

# 1.3.20.12

Data: 2022-08-11
Commit: 848f853

- Odznaka nowego poziomu używa awatara z serwera.

# 1.3.20.11

Data: 2022-08-10
Commit: 8c129da

- Polecenie połącz mówi, kto już jest połączony z danym kontem.

# 1.3.20.10

Data: 2022-08-09
Commit: b980601

- Polecenie kto chce odświeża liczbę chętnych.

# 1.3.20.9

Data: 2022-08-08
Commit: 4fea1fb

- Skorygowano CT za niszczenie kart SSS.

# 1.3.20.8

Data: 2022-08-08
Commit: 7e85cbc

- Poprawiono ulepszanie kart.

# 1.3.20.7

Data: 2022-08-06
Commit: a970e21

- Lista życzeń pokazuje, ile osób chce każdej postaci.

# 1.3.20.6

Data: 2022-08-06
Commit: 63e2886

- Lista aktywnych kart jest podzielona na strony.

# 1.3.20.5

Data: 2022-08-05
Commit: a155e9f

- Seria loterii kart pokazuje postęp.

# 1.3.20.4

Data: 2022-08-02
Commit: 0ed942f

- Skorygowano jakość przedmiotów z wypraw kart ultimate.

# 1.3.20.3

Data: 2022-08-02
Commit: d843f3c

- Nowe polecenie karcianka- (cpf-): pokazuje uproszczony profil karcianki.
- Polecenie karma zostało zastąpione nowym profilem.

# 1.3.20.2

Data: 2022-08-02
Commit: ee88419

- Poprawiono obsługę osób opuszczających serwer.

# 1.3.20.1

Data: 2022-08-01
Commit: 98ca81a

- Kartę ultimate można ulepszyć do wyższej jakości za pomocą ukończonej figurki.

# 1.3.20.0

Data: 2022-07-30
Commit: 8b5c224

- Polecenia kto chce z nickami zawsze dodają linki do Shindena.

# 1.3.19.47

Data: 2022-07-28
Commit: f8ebd55

- Automatyczne wyciszenie za ostrzeżenia następuje wcześniej.
- Poprawiono galerię.

# 1.3.19.46

Data: 2022-07-27
Commit: 4716188

- Lista życzeń i polecenia kto chce mogą dodać linki do profili na Shindenie.

# 1.3.19.45

Data: 2022-07-27
Commit: c16b337

- Nowe polecenie karma: pokazuje stan karmy.

# 1.3.19.44

Data: 2022-07-27
Commit: 66c97d6

- Poprawki narzędzi administracji.

# 1.3.19.43

Data: 2022-07-27
Commit: 96137bf

- Osoby z czarnej listy bota nie mogą używać części poleceń.

# 1.3.19.42

Data: 2022-07-27
Commit: e21bee5

- Wyszukiwanie właścicieli postaci może dodać linki do profili na Shindenie.

# 1.3.19.41

Data: 2022-07-26
Commit: f62aee0

- Kilka poleceń można używać tylko raz na jakiś czas, a bot mówi, ile trzeba poczekać.

# 1.3.19.40

Data: 2022-07-23
Commit: 724c4cf

- Nowy przedmiot: przepustka, czyli bilet na loterię, do zdobycia na wyprawach kart ultimate.
- Skorygowano przedmioty z wypraw kart ultimate.

# 1.3.19.39

Data: 2022-07-22
Commit: c7f63e1

- Administracja może wywołać safari.
- Poprawiono skrót polecenia donate.

# 1.3.19.38

Data: 2022-07-21
Commit: d2c52ea

- Poprawiono ograniczenie częstości używania poleceń.

# 1.3.19.37

Data: 2022-07-21
Commit: 6bc0bee

- Poprawiono tworzenie kart.

# 1.3.19.36

Data: 2022-07-19
Commit: 0e8b2cc

- Nowe polecenie daleko jeszcze? (how much to next packet): pokazuje, ile znaków brakuje do pakietu.
- Niektóre polecenia można używać tylko raz na jakiś czas.

# 1.3.19.35

Data: 2022-07-19
Commit: 38c820a

- Postęp do pakietu za aktywność nie ginie przy aktualizacji bota.

# 1.3.19.34

Data: 2022-07-19
Commit: 39b9e22

- Niektóre polecenia działają też dla samego bota.

# 1.3.19.33

Data: 2022-07-18
Commit: 92f6981

- Powód bana od nadzoru zawiera linki ze spamu.

# 1.3.19.32

Data: 2022-07-17
Commit: 498cec7

- Wyniki wyszukiwania pokazują nicki z serwera.

# 1.3.19.31

Data: 2022-07-17
Commit: d2bdfc2

- Polecenie lazyp działa poza kanałami poleceń od określonego poziomu.

# 1.3.19.30

Data: 2022-07-17
Commit: c4bc8c8

- Polecenie awatar może pokazać awatar z serwera.
- Poprawiono profil.

# 1.3.19.29

Data: 2022-07-16
Commit: abd1f68

- Nowe polecenie dla administracji clean config: usuwa z ustawień serwera skasowane kanały i role.

# 1.3.19.28

Data: 2022-07-16
Commit: 431e617

- Poprawiono darmowy skalpel za ulepszenie do SSS.

# 1.3.19.27

Data: 2022-07-16
Commit: 3221614

- Poprawiono darmowy skalpel za ulepszenie do SSS.

# 1.3.19.26

Data: 2022-07-16
Commit: 2c7b273

- Polecenie info pokazuje linki do strony, GitHuba, wiki i kart.

# 1.3.19.25

Data: 2022-07-16
Commit: bc7e455

- Poprawiono wyróżnienie wygranej w loterii.

# 1.3.19.24

Data: 2022-07-16
Commit: e1f8c85

- Polecenie wyprawa wysyła kilka kart naraz.
- Wygrana karty SSS w loterii ma wyróżniony komunikat.

# 1.3.19.23

Data: 2022-07-16
Commit: 623fbfa

- Lista przedmiotów przy tworzeniu kart przychodzi w prywatnej wiadomości.

# 1.3.19.22

Data: 2022-07-16
Commit: 0e3a144

- Długa lista przedmiotów przychodzi w prywatnej wiadomości, podzielona na strony.

# 1.3.19.21

Data: 2022-07-16
Commit: ed33b10

- Wyszukiwanie tytułów rozumie kilka skrótów.

# 1.3.19.20

Data: 2022-07-14
Commit: a6066d6

- Błąd tworzenia obrazka karty nie przerywa działania polecenia.

# 1.3.19.19

Data: 2022-07-14
Commit: 0f92e77

- Poprawiono serca listy życzeń w karcie+.

# 1.3.19.18

Data: 2022-07-14
Commit: 3bf7e12

- Bot lepiej radzi sobie z błędami połączenia z Shindenem.

# 1.3.19.17

Data: 2022-07-14
Commit: 747170c

- Poprawki wewnętrzne.

# 1.3.19.16

Data: 2022-07-13
Commit: 2fa8ab1

- Strona szybciej liczy, ile osób chce kart.

# 1.3.19.15

Data: 2022-07-13
Commit: 0670259

- Otwieranie pakietów działa szybciej.

# 1.3.19.14

Data: 2022-07-13
Commit: 2f1ab98

- Poprawki wewnętrzne.

# 1.3.19.13

Data: 2022-07-13
Commit: 6b70843

- Strona może wyszukiwać karty po numerze.
- Serca listy życzeń biorą pod uwagę postacie.

# 1.3.19.12

Data: 2022-07-13
Commit: 5a3de7e

- Gdy Shinden nie odpowiada, bot szybciej kończy próby i mówi o tym wprost.

# 1.3.19.11

Data: 2022-07-13
Commit: 4b983a5

- Zmieniono format zapisu obrazków kart.

# 1.3.19.10

Data: 2022-07-13
Commit: 0424f7d

- Powiadomienia ze strony mogą trafiać na kanały przez webhooki.

# 1.3.19.9

Data: 2022-07-13
Commit: 6efee0b

- Przebudowano otwieranie pakietów.

# 1.3.19.8

Data: 2022-07-12
Commit: 9d6d5ad

- Poprawki wewnętrzne.

# 1.3.19.7

Data: 2022-07-12
Commit: b2f3092

- Poprawiono rozmiar miniatur kart.

# 1.3.19.6

Data: 2022-07-02
Commit: 6d1de67

- Polecenie lazyp ma skrót lp.
- Nowy tag nie może być taki sam jak stary.

# 1.3.19.5

Data: 2022-07-01
Commit: 1ad6117

- Przeniesienie karty przez administrację usuwa ją z listy życzeń nowego właściciela.

# 1.3.19.4

Data: 2022-06-30
Commit: 219f6cb

- Nowe polecenie lazyp: szybko otwiera pierwszy pakiet z domyślnymi ustawieniami.

# 1.3.19.3

Data: 2022-06-30
Commit: 07ab995

- Otwieranie pakietu domyślnie sprawdza listy życzeń.

# 1.3.19.2

Data: 2022-06-30
Commit: f29b074

- Karty z safari, strony i tworzenia mają policzoną liczbę chętnych.

# 1.3.19.1

Data: 2022-06-28
Commit: 1b4c0fb

- Poprawiono reakcje w prywatnych wiadomościach.

# 1.3.19.0

Data: 2022-06-26
Commit: a6faddf

- Obrazki kart są w lżejszym formacie.
- Poprawiono położenie tekstu na profilach i kartach.

# 1.3.18.20

Data: 2022-06-21
Commit: 5865bd1

- Otwieranie pakietu może dodać tag do nowych kart.

# 1.3.18.19

Data: 2022-06-21
Commit: 5935a08

- Lista życzeń osoby, która opuściła serwer, przestaje się liczyć.

# 1.3.18.18

Data: 2022-06-20
Commit: fbba28b

- Poprawiono liczenie osób chcących danej postaci.

# 1.3.18.17

Data: 2022-06-20
Commit: f9dd2c5

- Poprawiono filtrowanie kart na stronie.

# 1.3.18.16

Data: 2022-06-20
Commit: 411dd82

- Liczba osób chcących danej postaci jest liczona na bieżąco.

# 1.3.18.15

Data: 2022-06-17
Commit: 6ad1907

- Strona może sortować karty po tym, ile osób ich chce.
- Wyśrodkowano statystyki na kartach ultimate.

# 1.3.18.14

Data: 2022-06-17
Commit: 54af9ca

- Karty ultimate można aktualizować.

# 1.3.18.13

Data: 2022-06-17
Commit: fb80e52

- Poprawiono zużywanie kilku części figurki naraz.

# 1.3.18.12

Data: 2022-06-17
Commit: f7738cc

- Nowe polecenie figurka koniec (figure end): zamienia gotową figurkę w kartę ultimate.
- Karty ultimate jota mają ramkę zależną od charakteru.
- Nie można zacząć drugiej figurki tej samej postaci.

# 1.3.18.11

Data: 2022-06-16
Commit: d7bf7a2

- Otwieranie pakietu może uwolnić niechciane karty zamiast je niszczyć.

# 1.3.18.10

Data: 2022-06-16
Commit: 3e7617b

- Otwieranie pakietu nie niszczy kart z twojej listy życzeń.

# 1.3.18.9

Data: 2022-06-16
Commit: 303da2c

- Nowa jakość kart ultimate: jota.
- Otwieranie pakietu może od razu zniszczyć karty, których mało kto chce.

# 1.3.18.8

Data: 2022-06-16
Commit: 5ba678f

- Doświadczenie ze skrzyni można przenieść na aktywną figurkę.

# 1.3.18.7

Data: 2022-06-15
Commit: 7f3b24a

- Karty zapamiętują, ile osób ich chce.

# 1.3.18.6

Data: 2022-06-13
Commit: 2666b99

- Poprawiono wybór części figurki o nazwie z kilku słów.

# 1.3.18.5

Data: 2022-06-13
Commit: f590074

- Zmiana budowanej części figurki zeruje jej punkty konstrukcji.
- Poprawiono zużywanie części figurki.

# 1.3.18.4

Data: 2022-06-13
Commit: aed277f

- Nowe polecenie wybierz element (select part): wybiera część figurki do budowy.
- Części figurki można zużyć, aby dodać punkty konstrukcji.

# 1.3.18.3

Data: 2022-06-08
Commit: ddc3152

- Nowe polecenie figurki (figures): pokazuje listę figurek i ustawia aktywną.
- Nowe polecenie figurka (figure): pokazuje szczegóły figurki.

# 1.3.18.2

Data: 2022-05-14
Commit: 6d8a112

- Poprawki wewnętrzne.

# 1.3.18.1

Data: 2022-05-14
Commit: abbdcaf

- Poprawiono wiadomość pożegnalną.

# 1.3.18.0

Data: 2022-05-14
Commit: d0aeb28

- Bot działa na nowszej wersji biblioteki Discorda.

# 1.3.17.37

Data: 2022-05-10
Commit: 3844915

- Relacja kart ultimate na łatwiejszych wyprawach nie spada poniżej pewnego poziomu.
- Otwieranie pakietu i karta+ pokazują, ile osób chce danej karty.
- Skorygowano wyprawy kart ultimate.

# 1.3.17.36

Data: 2022-01-13
Commit: 13b864b

- Skorygowano przedmioty z wypraw.

# 1.3.17.35

Data: 2022-01-13
Commit: c9336df

- Nowe wyprawy dla kart ultimate.
- Karty ultimate theta mają ramkę zależną od charakteru.
- Wydarzenia na wyprawach pokazują pełne statystyki karty.

# 1.3.17.34

Data: 2022-01-10
Commit: f5d2751

- Nowa jakość kart ultimate: theta.

# 1.3.17.33

Data: 2021-12-14
Commit: 541f41e

- Poprawiono zmianę koloru na ten sam kolor.

# 1.3.17.32

Data: 2021-12-04
Commit: 7e3b9a5

- Nowy przedmiot: marker, który przywraca karcie początkową wartość rynkową.

# 1.3.17.31

Data: 2021-12-03
Commit: a19d48c

- Zmieniono liczenie wartości rynkowej przy wymianie.

# 1.3.17.30

Data: 2021-12-01
Commit: 1dc664a

- Nowa ramka kart ultimate gamma, zależna od siły karty.

# 1.3.17.29

Data: 2021-11-20
Commit: 6eb019d

- Nadzór zapomina powtórzone wiadomości po pewnym czasie.

# 1.3.17.28

Data: 2021-11-03
Commit: 06bb859

- Poprawiono komunikat przełączania banowania za spam linkami.

# 1.3.17.27

Data: 2021-11-03
Commit: 0cf6ada

- Administracja może włączyć banowanie za spam linkami.
- Nadzór rozpoznaje więcej fałszywych linków.

# 1.3.17.26

Data: 2021-11-01
Commit: 1617801

- Nadzór rozpoznaje więcej fałszywych linków.

# 1.3.17.25

Data: 2021-10-29
Commit: b7462a1

- Nadzór rozpoznaje kolejny fałszywy link.

# 1.3.17.24

Data: 2021-10-29
Commit: 1abc091

- Nadzór rozpoznaje kolejny fałszywy link.

# 1.3.17.23

Data: 2021-10-28
Commit: 70261c4

- Nadzór banuje grupy kont o tej samej nazwie, które dołączają w krótkim czasie.

# 1.3.17.22

Data: 2021-10-28
Commit: 39c24b1

- Nadzór rozpoznaje kolejny fałszywy link.

# 1.3.17.21

Data: 2021-10-26
Commit: d2a51ea

- Nadzór od razu banuje za linki do znanych oszustw.
- Nadzór szybciej reaguje na spam.
- Bota można zrestartować i zaktualizować ze strony.

# 1.3.17.20

Data: 2021-10-13
Commit: c12da9a

- Poprawiono opis dużego pakietu kart.

# 1.3.17.19

Data: 2021-10-05
Commit: 932cddb

- Poprawiono liczenie wartości rynkowej przy wymianie.

# 1.3.17.18

Data: 2021-08-26
Commit: 9297305

- Bot może przyjąć więcej zadań naraz.

# 1.3.17.17

Data: 2021-08-13
Commit: 5e0462c

- Poprawki narzędzi administracji.

# 1.3.17.16

Data: 2021-08-12
Commit: b3f2102

- Nowa jakość kart ultimate: epsilon, z własną ramką.
- Administracja może zamienić kartę na kartę ultimate.

# 1.3.17.15

Data: 2021-08-09
Commit: e659297

- Poprawiono wydarzenia na wyprawach.

# 1.3.17.14

Data: 2021-08-09
Commit: 7b0c5d4

- Na wyprawach zdarzają się walki.
- Tagi nie mogą zawierać spacji.

# 1.3.17.13

Data: 2021-07-17
Commit: 0094cd8

- Zagadki mają losową kolejność odpowiedzi.
- Zaznaczenie kilku odpowiedzi w zagadce się nie liczy.

# 1.3.17.12

Data: 2021-07-17
Commit: bf05d27

- Administracja może dodawać i usuwać zagadki.

# 1.3.17.11

Data: 2021-07-17
Commit: ddb1091

- Nowe polecenie karta obrazek (card image): pokazuje obrazek karty.
- Niektóre polecenia kart działają poza kanałami poleceń po osiągnięciu określonego poziomu.
- Karta+ zawsze pokazuje serca listy życzeń.

# 1.3.17.10

Data: 2021-07-15
Commit: 6b5117b

- Strona może pobrać nazwę gracza z Shindena.

# 1.3.17.9

Data: 2021-07-01
Commit: b3f028e

- Administracja może ustawić, ile znaków daje punkt doświadczenia.

# 1.3.17.8

Data: 2021-06-17
Commit: 260861d

- Poprawiono filtrowanie kart po tagach na stronie.

# 1.3.17.7

Data: 2021-05-23
Commit: 4c45700

- Odrzucone zgłoszenie zostaje oznaczone jako odrzucone.

# 1.3.17.6

Data: 2021-05-22
Commit: 0572cc2

- Poprawiono zielone serce.

# 1.3.17.5

Data: 2021-05-19
Commit: 41803de

- Zielone serce przy otwieraniu pakietu oznacza kartę z twojej listy życzeń.

# 1.3.17.4

Data: 2021-05-19
Commit: 24a1ce1

- Poprawiono sortowanie po jakości na stronie.

# 1.3.17.3

Data: 2021-05-13
Commit: 2d88fb5

- Poprawiono obrazek na safari.

# 1.3.17.2

Data: 2021-05-13
Commit: e5f7017

- Obrazki kart są mniejsze i szybciej się ładują.

# 1.3.17.1

Data: 2021-05-05
Commit: b736986

- Nowe karty od razu mają policzoną moc.

# 1.3.17.0

Data: 2021-05-05
Commit: 731e5d2

- Moc karty jest zapamiętywana.
- Strona może sortować karty po mocy i filtrować po dowolnym z tagów.
- Polecenia kto chce mogą pokazywać nicki zamiast oznaczeń.

# 1.3.16.23

Data: 2021-04-30
Commit: b53dd07

- Wymiana obniża doświadczenie wymienianych kart.
- Skorygowano karmę i CT za uwalnianie i niszczenie kart oraz wartość rynkową.

# 1.3.16.22

Data: 2021-04-28
Commit: 616f81f

- Poprawiono ostrzeżenie o możliwym multikoncie.

# 1.3.16.21

Data: 2021-04-27
Commit: e801ec3

- Karta+ pokazuje, czy ktoś ma kartę na liście życzeń.
- Karty o charakterze Tsundere są tańsze w utrzymaniu na wyprawie.
- Bot próbuje ponownie połączyć się z Discordem zamiast się wyłączać.
- Skorygowano wyprawy i relację z przedmiotów.

# 1.3.16.20

Data: 2021-04-22
Commit: 241746c

- Sklepiki wymagają określonego poziomu.
- Otwieranie pakietu może sprawdzić, czy ktoś ma karty na liście życzeń.

# 1.3.16.19

Data: 2021-04-20
Commit: 67204c9

- Poprawiono awatary w wiadomościach bota.
- Profil karcianki nie pokazuje już PC.

# 1.3.16.18

Data: 2021-04-18
Commit: d0c62a7

- Przedmiot można kupić, mając dokładnie tyle waluty, ile kosztuje.
- Skorygowano wpływ przedmiotów na relację.

# 1.3.16.17

Data: 2021-04-17
Commit: 4393718

- Polecenie pakiet otwiera kilka pakietów naraz.

# 1.3.16.16

Data: 2021-04-17
Commit: b3454b2

- Nowy przedmiot: duży pakiet kart.
- Pakiety z kiosku mają własne źródło kart.
- Skorygowano ceny w kiosku.

# 1.3.16.15

Data: 2021-04-17
Commit: 5ef2e03

- Poprawiono odbieranie nagród za tygodniowe misje.

# 1.3.16.14

Data: 2021-04-15
Commit: 3732eda

- Poprawiono ograniczenie doświadczenia za szybkie pisanie.

# 1.3.16.13

Data: 2021-04-15
Commit: f850ea4

- Pisanie wielu wiadomości w krótkim czasie daje mniej doświadczenia.
- Skorygowano dzienny limit pakietów za aktywność.

# 1.3.16.12

Data: 2021-04-15
Commit: d8b6965

- Wyciszeni gracze nie mogą wygrać safari ani loterii kart.
- Sprawdzanie nicku pokazuje, gdy nie udało się pobrać profilu z Shindena.

# 1.3.16.11

Data: 2021-04-14
Commit: 69a7e75

- Osoby bez awatara mają domyślny awatar Discorda.

# 1.3.16.10

Data: 2021-04-13
Commit: a0bcfe6

- Poprawki wewnętrzne.

# 1.3.16.9

Data: 2021-04-13
Commit: 763c819

- Strona może dać graczowi pakiety i od razu je otworzyć.

# 1.3.16.8

Data: 2021-04-12
Commit: 5fbabd4

- Ranking buduje się szybciej i pokazuje informację o budowaniu.
- Poprawiono sprawdzanie mocy talii.

# 1.3.16.7

Data: 2021-04-12
Commit: 5fdeba3

- Poprawiono szukanie przeciwnika w pojedynkach.

# 1.3.16.6

Data: 2021-04-12
Commit: 04f9409

- Moc talii jest zapamiętywana, więc szukanie przeciwnika w pojedynkach działa szybciej.

# 1.3.16.5

Data: 2021-04-12
Commit: d6a093f

- Poprawki wewnętrzne.

# 1.3.16.4

Data: 2021-04-10
Commit: ee8159d

- Nowe polecenie kiosk (ac shop): sklep, w którym płaci się AC.

# 1.3.16.3

Data: 2021-04-09
Commit: c2c7d8d

- Nowe rankingi PC i AC.
- Pakiety za aktywność mają dzienny limit.
- Polecenie misje działa na kanałach poleceń.
- Skorygowano karmę za uwalnianie kart.

# 1.3.16.2

Data: 2021-04-09
Commit: 925ae5b

- Tygodniowe misje liczą postęp.

# 1.3.16.1

Data: 2021-04-08
Commit: 4acc29a

- Nowe polecenie misje (quest): pokazuje postęp misji i odbiera nagrody.
- Dzienne i tygodniowe misje nagradzają nową walutą AC.
- Portfel pokazuje AC.

# 1.3.16.0

Data: 2021-04-08
Commit: 2b0b9ce

- Przygotowanie pod dzienne misje.

# 1.3.15.6

Data: 2021-04-08
Commit: 5fc2712

- Nowe polecenie życzenia filtr (wishlistf): pokazuje pozycje z listy życzeń gracza, które ma wskazana osoba.
- Usunięto polecenie życzenia użytkownik.
- Poprawiono nagrodę za co którąś kartę ulepszoną do SSS.

# 1.3.15.5

Data: 2021-04-06
Commit: b839f71

- Strona może zmienić nick gracza na serwerze.
- Uczestnicy loterii są tasowani.

# 1.3.15.4

Data: 2021-04-05
Commit: 96954cf

- Administracja może uruchomić kilka loterii kart z rzędu.

# 1.3.15.3

Data: 2021-04-05
Commit: 7607544

- Wynik loterii kart pojawia się jako nowa wiadomość.

# 1.3.15.2

Data: 2021-04-04
Commit: bace1df

- Poprawki narzędzi administracji.

# 1.3.15.1

Data: 2021-04-04
Commit: a793cf5

- Loteria kart losuje karty i wysyła zwycięzcy prywatną wiadomość.

# 1.3.15.0

Data: 2021-04-04
Commit: ba6b7c5

- Poprawki narzędzi administracji.

# 1.3.14.9

Data: 2021-04-03
Commit: b590ab5

- Ranking poziomów sortuje po doświadczeniu.
- Skorygowano karmę za wyprawy.
- Poprawiono listę poleceń w pomocy.

# 1.3.14.8

Data: 2021-04-03
Commit: 2c19e75

- Tworzenie kart uwzględnia jakość przedmiotów.
- Skorygowano wartość niektórych przedmiotów przy tworzeniu kart.

# 1.3.14.7

Data: 2021-04-02
Commit: abefd86

- Klątwy na kartach mają efekty: słabsze statystyki, blokady i odwrócone działanie przedmiotów.
- Czwarty poziom skrzyni doświadczenia.
- Karta może mieć ograniczoną liczbę dostępnych ulepszeń.
- Skorygowano wpływ charakteru na moc karty.

# 1.3.14.6

Data: 2021-03-29
Commit: 57621cd

- Polecenie wylosuj wymaga co najmniej dwóch opcji.

# 1.3.14.5

Data: 2021-03-29
Commit: fae9fb4

- Nowe polecenie wylosuj (one from many): bot wybiera jedną z podanych opcji.
- Nowe polecenie dla moderacji pozycja gracza: losuje graczom numery.

# 1.3.14.4

Data: 2021-03-29
Commit: 53d0d18

- Nowe polecenie dla moderacji pary: losuje pary liczb.
- Poprawiono sprawdzanie roli moderatora.

# 1.3.14.3

Data: 2021-03-28
Commit: 2c4b369

- Rozwiązanie zgłoszenia może dać ostrzeżenie zamiast wyciszenia, a zbyt wiele ostrzeżeń kończy się automatycznym wyciszeniem.
- Nowe polecenie dla moderacji quote: cytuje wiadomość na inny kanał.

# 1.3.14.2

Data: 2021-03-25
Commit: eafa6a9

- Wyszukiwanie kart może pokazywać nicki zamiast oznaczeń.
- Długie wyniki wyszukiwania właścicieli postaci przychodzą w prywatnej wiadomości.

# 1.3.14.1

Data: 2021-03-11
Commit: 9e6e25f

- Poprawki wewnętrzne.

# 1.3.14.0

Data: 2021-03-08
Commit: 0cd8af6

- Przygotowanie pod klątwy na kartach.

# 1.3.13.11

Data: 2021-03-08
Commit: 8f70017

- Ostrzeżenie o możliwym multikoncie podaje więcej informacji.

# 1.3.13.10

Data: 2021-03-07
Commit: 73bd17b

- Poprawki narzędzi administracji.

# 1.3.13.9

Data: 2021-03-06
Commit: 3ccd45e

- Nowe polecenia do wyglądu profilu na stronie waifu: kolor strony, szczegół strony oraz położenie tła i szczegółu.

# 1.3.13.8

Data: 2021-03-05
Commit: 00cb7d1

- Przygotowanie pod nowe ustawienia wyglądu profilu na stronie.
- Poprawiono wyprawy trwające bardzo krótko.

# 1.3.13.7

Data: 2021-03-03
Commit: bd54ab9

- Nowe ikony kart: własna ramka i karta w galerii.

# 1.3.13.6

Data: 2021-03-03
Commit: 0507e0e

- Nowe ikony kart: aktywna, niska i wysoka wartość rynkowa.
- Strona pokazuje więcej informacji o kartach.

# 1.3.13.5

Data: 2021-02-28
Commit: 5d13b42

- Nowe polecenie tło strony (site background): zmienia tło profilu na stronie waifu.
- Styl i tło profilu można kupić za TC.
- Karty na wyprawie mają ikonę.
- Skorygowano ceny zmian w profilu i rangi globalnych emotek.

# 1.3.13.4

Data: 2021-02-27
Commit: 8e5e3a5

- Wyprawy kosztują mniej karmy, a neutralna karma ma własny bonus.
- Poprawiono opis jednego z wydarzeń na wyprawie.

# 1.3.13.3

Data: 2021-02-27
Commit: 083e531

- Strona pokazuje portfel, karmę, tytuł i moc kart.
- Poprawiono obrazki kart ultimate.

# 1.3.13.2

Data: 2021-02-26
Commit: 83681f6

- Status wypraw pokazuje, kiedy karta się zmęczy.
- Nie można wysłać na wyprawę karty, która od razu by się zmęczyła.

# 1.3.13.1

Data: 2021-02-26
Commit: 55a5683

- Skorygowano nagrody i koszty wypraw.
- Administracja może kasować karty.

# 1.3.13.0

Data: 2021-02-26
Commit: 2ec2ba0

- Nowa ramka kart ultimate, a starsze mają osobną warstwę statystyk.

# 1.3.12.10

Data: 2021-02-25
Commit: 04d3a90

- Pojedynki liczą obrażenia dokładniej.
- Poprawiono nadzór.
- Skorygowano koszty wypraw.

# 1.3.12.9

Data: 2021-02-23
Commit: 68aa188

- Poprawiono czas wyprawy potrzebny do jednego z wydarzeń.

# 1.3.12.8

Data: 2021-02-23
Commit: 68b0aaf

- Poprawiono literówki w komunikatach bota.

# 1.3.12.7

Data: 2021-02-22
Commit: 8d25f92

- Po wyprawie widać łączne doświadczenie karty.
- Strona pokazuje karty na wyprawach.

# 1.3.12.6

Data: 2021-02-22
Commit: 4cadb0b

- Osłabiono charakter Yato.
- Poprawiono jedno z wydarzeń na wyprawie.

# 1.3.12.5

Data: 2021-02-22
Commit: f0d03c6

- Bardzo krótka wyprawa daje mniej doświadczenia.
- Skorygowano koszty wypraw.
- Poprawiono wyszukiwanie kart z tytułu.

# 1.3.12.4

Data: 2021-02-22
Commit: 95833ce

- Na wyprawach zdarzają się losowe wydarzenia, dobre i złe.
- Pakiety z wypraw mają własne źródło kart.

# 1.3.12.3

Data: 2021-02-21
Commit: cc5b2c9

- Zmiana obrazka profilu podaje dokładniejszy powód błędu.

# 1.3.12.2

Data: 2021-02-21
Commit: 508b380

- Dłuższa wyprawa daje proporcjonalnie lepsze nagrody.
- Niektóre wyprawy dają przedmioty o różnej jakości.
- Skorygowano nagrody i koszty wypraw.

# 1.3.12.1

Data: 2021-02-21
Commit: 1aa9adc

- Wyprawy działają: karta zbiera doświadczenie i przedmioty, a po powrocie zgłasza nagrody.
- Nowe polecenie wyprawa status (expedition status): pokazuje karty na wyprawach.
- Nowe polecenie wyprawa koniec (expedition end): kończy wyprawę karty.
- Karty na wyprawie nie mogą brać udziału w innych akcjach.

# 1.3.12.0

Data: 2021-02-16
Commit: c27f7ee

- Nowe polecenie wyprawa (expedition): wysyła kartę na wyprawę.
- Usunięto areny.
- Polecenia administracji mają pierwszeństwo przed zwykłymi.

# 1.3.11.15

Data: 2021-02-12
Commit: dbd9bd0

- Poprawki wewnętrzne.

# 1.3.11.14

Data: 2021-02-12
Commit: 6347809

- Bot ostrzega administrację, gdy ktoś próbuje połączyć już połączone konto Shindena.
- Areny dają przedmioty o różnej jakości.
- Skorygowano nagrody i koszty na arenach.
- Poprawiono wymianę.

# 1.3.11.13

Data: 2021-02-10
Commit: 5d9893b

- Odświeżona ramka kart ultimate.

# 1.3.11.12

Data: 2021-02-09
Commit: 9085a8e

- Pełny opis karty pokazuje ikony tagów.

# 1.3.11.11

Data: 2021-02-08
Commit: a2d2e9c

- Strona może pobrać listę życzeń gracza.
- Galeria i waifu na stronie pokazują najlepsze karty.

# 1.3.11.10

Data: 2021-02-08
Commit: aa2c7a6

- Poprawiono ustawienia kanałów bez liczenia wiadomości.

# 1.3.11.9

Data: 2021-02-08
Commit: ea32538

- Pojedynki mogą mieć osobny kanał.
- Administracja może ustawić kanały, na których wiadomości się nie liczą.

# 1.3.11.8

Data: 2021-02-08
Commit: f116901

- Poprawki wewnętrzne.

# 1.3.11.7

Data: 2021-02-07
Commit: 4518853

- Kolekcja ma limit kart.
- Nowe polecenie limit kart (card limit): zwiększa limit kart za TC.
- Nowe polecenie galeria (gallery): zwiększa liczbę miejsc w galerii za TC.

# 1.3.11.6

Data: 2021-02-07
Commit: 64fc8c1

- Nowe polecenie oznacz usuń (tag remove): zdejmuje wybrany tag z kart.
- Nowe polecenie zasady wymiany (exchange conditions): ustawia twoje warunki wymiany.

# 1.3.11.5

Data: 2021-02-06
Commit: b459b0a

- Strona może sortować karty po większej liczbie cech.

# 1.3.11.4

Data: 2021-02-06
Commit: f88c926

- Poprawki wewnętrzne.

# 1.3.11.3

Data: 2021-02-06
Commit: 3561b9d

- Poprawki narzędzi administracji.

# 1.3.11.2

Data: 2021-02-04
Commit: 1a5f71a

- Poprawiono wymianę i wyszukiwanie postaci z tytułu.

# 1.3.11.1

Data: 2021-02-04
Commit: e1ed9ff

- Poprawiono pojedynki i działanie strony po aktualizacji.

# 1.3.11.0

Data: 2021-01-31
Commit: ceb2a86

- Bot działa na nowszej wersji platformy.
- Poprawiono komunikat po banie.

# 1.3.10.9

Data: 2021-01-19
Commit: f4bd1b3

- Oddanie w wymianie karty waifu obniża jej relację i zdejmuje ją z waifu.

# 1.3.10.8

Data: 2021-01-19
Commit: da9e6f7

- Profil karcianki na stronie pokazuje warunki wymiany.
- Poprawiono literówki.

# 1.3.10.7

Data: 2021-01-17
Commit: 1082921

- Profil karcianki na stronie pokazuje używane tagi.

# 1.3.10.6

Data: 2021-01-16
Commit: c447c5d

- Obrazki kart z profilu są przechowywane osobno i dostępne dla strony.

# 1.3.10.5

Data: 2021-01-15
Commit: 1eee035

- Strona może wyszukiwać, sortować i filtrować karty gracza po tagach.

# 1.3.10.4

Data: 2021-01-15
Commit: 1fba0de

- Strona może wyświetlać karty gracza po kawałku.

# 1.3.10.3

Data: 2020-12-04
Commit: c7b4f15

- Poprawiono liczenie wartości rynkowej przy wymianie.

# 1.3.10.2

Data: 2020-11-30
Commit: 27002a8

- Poprawiono zamykanie wymiany.

# 1.3.10.1

Data: 2020-11-27
Commit: 1f31812

- Przygotowanie pod wyprawy kart.

# 1.3.10.0

Data: 2020-11-27
Commit: 3959957

- Karty ultimate pokazują jakość zamiast rangi.
- Wymiana bierze pod uwagę jakość kart przy liczeniu ich wartości.
- Skorygowano karmę i CT za uwalnianie i niszczenie kart.

# 1.3.9.14

Data: 2020-11-10
Commit: 77ffd2b

- Poprawiono wyświetlanie zdobytych przedmiotów.

# 1.3.9.13

Data: 2020-11-10
Commit: 978ce70

- Skorygowano spadek relacji i karmy na arenie oraz doświadczenie z poświęcania.

# 1.3.9.12

Data: 2020-11-06
Commit: 0530b22

- Lista życzeń może ukryć karty niewymienialne.
- Nie można dwa razy otworzyć tego samego pakietu przez stronę.

# 1.3.9.11

Data: 2020-10-08
Commit: ec44358

- Mniej pojedynków dziennie, ale więcej punktów i PC za każdy.
- Skorygowano rangi PVP.

# 1.3.9.10

Data: 2020-09-16
Commit: aa14eb0

- Karty z własnym obrazkiem mają ikonę.
- Nowe kolory do kupienia.
- Poprawiono liczenie wartości rynkowej przy wymianie.

# 1.3.9.9

Data: 2020-08-24
Commit: de0f366

- Karty ultimate z gotowych szkieletów nie mogą być wymieniane.
- Nie można dostać w wymianie drugiej karty ultimate tej samej postaci.
- Lista życzeń pokazuje też pozycje, których nie udało się pobrać z Shindena.
- Zapowiedzi tytułów są oznaczone jako Deklaracja.

# 1.3.9.8

Data: 2020-07-06
Commit: 872de55

- Nowa ramka kart ultimate.

# 1.3.9.7

Data: 2020-07-06
Commit: 2950bb5

- Poprawiono rynek i czarny rynek.

# 1.3.9.6

Data: 2020-07-06
Commit: 6c45bba

- Nowe części figurek: głowa, tułów, ręce, nogi i ciuchy.
- Części montuje się do aktywnej figurki.
- Przedmioty z rynku mogą mieć różną jakość.

# 1.3.9.5

Data: 2020-07-05
Commit: 9ddbbfc

- Nowe ramki kart ultimate.

# 1.3.9.4

Data: 2020-07-03
Commit: 2543d5f

- Szkielet użyty na karcie SSS rozpoczyna budowę figurki.
- Niektóre przedmioty można użyć bez wskazywania karty.
- Lepsza jakość przedmiotu daje więcej doświadczenia i relacji.

# 1.3.9.3

Data: 2020-07-02
Commit: d07951c

- Pomoc pokazuje też polecenia administracji osobom z uprawnieniami.
- Nowa ramka kart ultimate.

# 1.3.9.2

Data: 2020-07-02
Commit: 5c3a5d1

- Skorygowano moc kart i obrażenia kart ultimate.

# 1.3.9.1

Data: 2020-07-02
Commit: 6357c11

- Przedmioty mogą mieć jakość, która zwiększa ich działanie.
- Nowe przedmioty: szkielety figurek i uniwersalne części.
- W koszarach można kupić gotowe szkielety figurek Asuny, Gintokiego i Megumin.
- Karty ultimate mają własne ramki zależne od jakości.

# 1.3.9.0

Data: 2020-07-01
Commit: 4bbdbdf

- Początki figurek: z kart będzie można budować figurki, a z nich karty ultimate.
- Karty ultimate nie biorą udziału w arenach, rynkach i wskrzeszaniu.
- Safari pojawia się rzadziej.

# 1.3.8.14

Data: 2020-06-30
Commit: a44ea28

- Gracze PVP mają rangi nazwane jak stopnie w rzymskiej armii.
- Portfel pokazuje CT i PC.
- Skorygowano ceny w koszarach, sklepiku i kilku poleceniach.
- Bot przestał reagować emotkami na wiadomości o sobie.

# 1.3.8.13

Data: 2020-06-30
Commit: d231946

- Nowe polecenie koszary (pvp shop): sklep, w którym płaci się punktami z pojedynków (PC).
- Plastelina jest dostępna w sklepiku.

# 1.3.8.12

Data: 2020-06-29
Commit: 3bc8ece

- Lepsza skrzynia doświadczenia zbiera więcej doświadczenia.
- Skorygowano koszty skrzyni doświadczenia.
- Poprawiono wybór obrazka plasteliną.

# 1.3.8.11

Data: 2020-06-29
Commit: b82789f

- Plastelina pokazuje listę obrazków postaci z Shindena i pozwala wybrać jeden.
- Nowe stopnie skrajnej relacji z kartą.

# 1.3.8.10

Data: 2020-06-28
Commit: 46ddbe0

- Osobne dzienne limity przedmiotów dla różnych aren.
- Wartość rynkowa karty wpływa na CT za jej zniszczenie.

# 1.3.8.9

Data: 2020-06-22
Commit: e75089a

- Bot reaguje emotkami na niektóre wiadomości o sobie.

# 1.3.8.8

Data: 2020-06-22
Commit: e880a73

- Poprawiono skróty polecenia idp.

# 1.3.8.7

Data: 2020-06-22
Commit: 2657546

- Nowy system punktów PVP z bonusem za serię zwycięstw.
- Poprawiono statystyki na stronie.

# 1.3.8.6

Data: 2020-06-21
Commit: 4c37ee0

- Poprawiono kolor nicku w profilu.
- Skorygowano punkty rankingu PVP.

# 1.3.8.5

Data: 2020-06-18
Commit: 6417d77

- Zgłoszenie własnej wiadomości proponuje wyciszenie.
- Włączenie lub wyłączenie karty pokazuje moc talii.
- Poprawiono nazwy rankingów PVP.

# 1.3.8.4

Data: 2020-06-18
Commit: 2d82c0a

- Poprawiono szukanie przeciwnika.

# 1.3.8.3

Data: 2020-06-17
Commit: 78fe35e

- Zmieniono dobieranie przeciwników w pojedynkach.

# 1.3.8.2

Data: 2020-06-17
Commit: c07ba4d

- Nie można trafić na samego siebie w pojedynku.
- Poprawiono liczenie poziomu gry.

# 1.3.8.1

Data: 2020-06-17
Commit: 3c0518f

- Przeciwnicy w pojedynkach są dobierani według poziomu gry.
- Za pojedynki dostaje się puzzle oraz punkty rankingu globalnego i sezonowego.
- Profil karcianki pokazuje statystyki PVP.

# 1.3.8.0

Data: 2020-06-15
Commit: c9bc130

- Nowe pojedynki: bot sam dobiera przeciwnika, a gracz ma dzienny limit walk.
- Talia nie może być ani zbyt silna, ani zbyt słaba.
- Można mieć więcej niż trzy aktywne karty.
- Nowe rankingi PVP: globalny i miesięczny.
- Usunięto masakrę.

# 1.3.7.28

Data: 2020-06-15
Commit: b840ad0

- Karty SSS mają określone doświadczenie potrzebne do ulepszenia.
- Poprawiono polecenie idp i kanały, na których działają polecenia profilu.

# 1.3.7.27

Data: 2020-06-15
Commit: a13a23f

- Nowe polecenie idp (iledopoziomu): pokazuje, ile doświadczenia brakuje do następnego poziomu.
- Opis karty pokazuje, ile doświadczenia potrzeba do ulepszenia.

# 1.3.7.26

Data: 2020-06-02
Commit: a2f185c

- Poprawiono opis polecenia zmiany tła profilu.

# 1.3.7.25

Data: 2020-06-01
Commit: 42d48cf

- Strona może pokazać profil karcianki gracza z galerią kart oznaczonych tagiem galeria.

# 1.3.7.24

Data: 2020-04-28
Commit: 45e0e16

- Poprawki wewnętrzne.

# 1.3.7.23

Data: 2020-04-27
Commit: a8fd24d

- Poprawki wewnętrzne.

# 1.3.7.22

Data: 2020-04-27
Commit: f424bd7

- Cytaty nie liczą się do znaków potrzebnych do doświadczenia i pakietów.

# 1.3.7.21

Data: 2020-03-05
Commit: d7285fa

- Poprawiono wyszukiwanie ulubionych postaci.

# 1.3.7.20

Data: 2020-03-03
Commit: e8255dd

- Z listy życzeń można usunąć kilka pozycji naraz.

# 1.3.7.19

Data: 2020-02-28
Commit: a56412f

- Poprawiono wymianę bez kart po jednej stronie.

# 1.3.7.18

Data: 2020-02-28
Commit: c93f55c

- Poprawiono liczenie wartości rynkowej przy wymianie.

# 1.3.7.17

Data: 2020-02-28
Commit: 7f0c14c

- Wartość rynkowa karty wpływa na karmę przy uwalnianiu i niszczeniu.
- Skorygowano dzienny limit przedmiotów.
- Poprawiono walki na arenie.

# 1.3.7.16

Data: 2020-02-28
Commit: 23d3743

- Dzienny limit przedmiotów zdobywanych w walkach.

# 1.3.7.15

Data: 2020-02-28
Commit: 31977d1

- Karty mają ukrytą wartość rynkową, która zmienia się przy wymianach.

# 1.3.7.14

Data: 2020-02-26
Commit: 4c52f75

- Poprawiono zgłoszenia i narzędzia administracji.

# 1.3.7.13

Data: 2020-02-24
Commit: 9b5b43d

- Nowy przedmiot: plastelina, która pozwala wybrać inny obrazek postaci z Shindena.

# 1.3.7.12

Data: 2020-02-20
Commit: 1b5a4df

- Skrzynia doświadczenia ma trzy poziomy z różnymi limitami i kosztami.
- Skorygowano doświadczenie trafiające do skrzyni.

# 1.3.7.11

Data: 2020-02-20
Commit: 266f4d7

- Odświeżono wygląd gwiazdek.
- Poprawiono kolory na liście kolorów.

# 1.3.7.10

Data: 2020-02-20
Commit: a209530

- Nowe kolory do kupienia.
- Poprawiono wygląd listy kolorów.

# 1.3.7.9

Data: 2020-02-19
Commit: 801eb60

- Polecenia profilu działają też na kanałach poleceń kart.
- Listę kart można przefiltrować po kilku tagach naraz.
- Poprawiono opisy poleceń.

# 1.3.7.8

Data: 2020-02-18
Commit: 3a5b5ad

- Poprawiono wygląd karty w profilu.

# 1.3.7.7

Data: 2020-02-18
Commit: c8b2766

- Poprawiono własną ramkę karty.

# 1.3.7.6

Data: 2020-02-18
Commit: 33df09e

- Polecenie oznacz dodaje tag do kilku kart naraz.
- Nowe polecenie oznacz czyść (tag clean): zdejmuje tagi z kart.

# 1.3.7.5

Data: 2020-02-18
Commit: 6d713c7

- Poprawiono wyświetlanie tagów i list życzeń.

# 1.3.7.4

Data: 2020-02-18
Commit: d93824e

- Karta może mieć kilka osobnych tagów.

# 1.3.7.3

Data: 2020-02-18
Commit: e3dffc1

- Nowa, sprawniejsza lista życzeń, która zapamiętuje nazwy wpisów.

# 1.3.7.2

Data: 2020-02-18
Commit: c87016a

- Bardzo niska karma daje dodatkowy efekt.
- Ikona wymiany na karcie wyświetla się zawsze.

# 1.3.7.1

Data: 2020-02-17
Commit: f061701

- Nowe przedmioty: stempel, który zmienia styl gwiazdek, i nożyczki, które ustawiają ramkę karty w profilu.
- Kilka przedmiotów zwiększających liczbę ulepszeń można użyć naraz.
- Poświęcanie kart daje więcej doświadczenia.
- Skorygowano ceny w sklepie.

# 1.3.7.0

Data: 2020-02-17
Commit: 3dd91a1

- Skrzynia doświadczenia ma poziomy i limit pojemności.
- Nowe polecenie tworzenie skrzyni (make chest): tworzy lub ulepsza skrzynię za karty SSS i krople krwi.
- Polecenie skrzynia przenosi doświadczenie ze skrzyni na kartę.

# 1.3.6.6

Data: 2020-02-16
Commit: 5c96414

- Nowy styl gwiazdek: wąż.

# 1.3.6.5

Data: 2020-02-13
Commit: f80709a

- Nowy styl gwiazdek: świnka.

# 1.3.6.4

Data: 2020-02-13
Commit: 1722822

- Gwiazdki na kartach mają kilka stylów.
- Karta w profilu może mieć własną ramkę.
- Poprawiono wyświetlanie wieku i wymiarów postaci.

# 1.3.6.3

Data: 2020-02-12
Commit: d839a0e

- Poprawiono otwieranie pakietów przez stronę.

# 1.3.6.2

Data: 2020-02-12
Commit: 49dbab0

- Nowe polecenie skrzynia (chest): tworzy skrzynię doświadczenia z karty SSS i kropel krwi.

# 1.3.6.1

Data: 2020-02-11
Commit: 9c1cf5e

- Część doświadczenia poświęcanych i uwalnianych kart trafia do skrzyni doświadczenia.

# 1.3.6.0

Data: 2020-02-11
Commit: 394aec9

- Nowe przedmioty: mleko truskawkowe i gorąca czekolada, które dodają karcie doświadczenia.
- Karma wpływa na to, jak często pojawia się czarny rynek.
- Karty ze strony mają źródło Strona.
- Skorygowano szanse na przedmioty.

# 1.3.5.2

Data: 2020-02-11
Commit: b89d925

- Karty z bardzo niską relacją nie mogą być wskrzeszone.
- Wskrzeszanie obniża relację karty i karmę gracza.
- Brak doświadczenia do ulepszenia pokazuje, ile go potrzeba.
- Skorygowano nagrody za wskrzeszanie i ulepszanie.

# 1.3.5.1

Data: 2020-02-10
Commit: 1fd98a6

- Poprawiono nagrodę za pierwsze ulepszenie do SSS.

# 1.3.5.0

Data: 2020-02-10
Commit: b09160c

- Wskrzeszanie karty co jakiś czas daje skalpel.
- Polecenie aktualizuj może przywrócić obrazek ze strony.
- Skalpel za ulepszenie do SSS dostaje się tylko za pierwszą taką kartę.

# 1.3.4.2

Data: 2020-02-07
Commit: 98b8d04

- Poprawiono wybór rodzaju gwiazdek.

# 1.3.4.1

Data: 2020-02-06
Commit: 9cca864

- Poprawiono liczenie gwiazdek.

# 1.3.4.0

Data: 2020-02-06
Commit: 16438fa

- Nowy wygląd gwiazdek na karcie, z większą liczbą poziomów.
- Skorygowano bonus do ataku za gwiazdki.

# 1.3.3.2

Data: 2020-02-03
Commit: da7141f

- Gwiazdki są wyśrodkowane na karcie.
- Skorygowano bonus do ataku za gwiazdki.

# 1.3.3.1

Data: 2020-01-24
Commit: f2aa00c

- Nowe unikalne karty, oznaczone ikoną.
- Listę kart można przefiltrować na unikaty.

# 1.3.2.9

Data: 2020-01-22
Commit: 3ce80e5

- Poprawiono położenie gwiazdek na karcie.

# 1.3.2.8

Data: 2020-01-22
Commit: d7860d0

- Wskrzeszanie karty dodaje gwiazdki na obrazku i bonus do ataku.

# 1.3.2.7

Data: 2020-01-20
Commit: ddb40ec

- Podgląd karty zawsze pokazuje aktualne dane.

# 1.3.2.6

Data: 2020-01-09
Commit: 97b99cf

- Administracja może wyrzucić lub zbanować kilka osób naraz.

# 1.3.2.5

Data: 2020-01-09
Commit: 3a50d3d

- Zgłoszenie zawiera link do zgłoszonej wiadomości i jest oznaczane jako rozpatrzone.
- Wiadomość o wyciszeniu podaje powód.

# 1.3.2.4

Data: 2019-11-27
Commit: 5fc2568

- Walka na arenie wyświetla się bez obrazka, ale z opisem przebiegu.

# 1.3.2.3

Data: 2019-11-21
Commit: 19ede8e

- Poprawiono wyszukiwanie na listach życzeń.

# 1.3.2.2

Data: 2019-11-21
Commit: 8e63800

- Nowe polecenie kto chce anime (who wants anime): pokazuje, kto ma tytuł na liście życzeń.

# 1.3.2.1

Data: 2019-11-21
Commit: 4cd73a4

- Nowe polecenie kto chce (who wants): pokazuje, kto ma kartę na liście życzeń.

# 1.3.2.0

Data: 2019-11-19
Commit: 5326373

- Strona może dodawać graczom kilka pakietów naraz.

# 1.3.1.10

Data: 2019-11-05
Commit: f56a578

- Wyniki wyszukiwania kart pokazują jakość karty.
- Opis karty pokazuje jej charakter.

# 1.3.1.9

Data: 2019-11-04
Commit: 663aad8

- Lista członków krainy pokazuje wszystkich członków.
- Lista wyciszonych pomija osoby, których nie ma już na serwerze.

# 1.3.1.8

Data: 2019-10-21
Commit: b6a7c7a

- Poprawki wewnętrzne.

# 1.3.1.7

Data: 2019-10-21
Commit: 5cb3706

- Pakiety kart można otwierać przez stronę.

# 1.3.1.6

Data: 2019-10-21
Commit: b4c6e64

- Nowe polecenie życzenia użytkownik (wishlist user): pokazuje karty z twojej listy życzeń, które ma wskazany gracz.

# 1.3.1.5

Data: 2019-10-17
Commit: e22f8f5

- Poprawiono dodawanie pakietów przez stronę.

# 1.3.1.4

Data: 2019-09-11
Commit: 2281416

- Poprawiono zmianę waifu: spadek relacji obejmuje wszystkie karty poprzedniej postaci.

# 1.3.1.3

Data: 2019-09-09
Commit: 0775a61

- Poprawki wewnętrzne.

# 1.3.1.2

Data: 2019-09-09
Commit: d366ada

- Strona może zaktualizować obrazek, imię i tytuł postaci na wszystkich jej kartach.

# 1.3.1.1

Data: 2019-09-09
Commit: 226f4f2

- Poprawki wewnętrzne.

# 1.3.1.0

Data: 2019-09-02
Commit: 51dd000

- Poprawiono odświeżanie obrazków kart.

# 1.3.0.9

Data: 2019-08-31
Commit: 496ba30

- Nadzór nie karze osób, które są już wyciszone.
- Administracja może ustawić graczowi poziom.

# 1.3.0.8

Data: 2019-08-29
Commit: d08a9da

- Poprawki wewnętrzne.

# 1.3.0.7

Data: 2019-08-29
Commit: 563aea6

- Wynik walki pokazuje łączne doświadczenie karty.
- Poprawiono sprawdzanie, czy karta może zostać demonem lub aniołem.

# 1.3.0.6

Data: 2019-08-29
Commit: 6f011be

- Liczba znaków potrzebna do pakietu za aktywność jest ustawiana przez administrację.

# 1.3.0.5

Data: 2019-08-27
Commit: d08094b

- Tworzenie kart działa: im cenniejsze przedmioty, tym lepsza jakość karty.
- Karta zapamiętuje ostatniego właściciela, więc administracja może przywrócić utracone karty.

# 1.3.0.4

Data: 2019-08-26
Commit: 4e089aa

- Nowe polecenie tworzenie (crafting): otwiera menu, w którym dodaje się przedmioty na nową kartę.
- Polecenie przedmioty ma nowe skróty.

# 1.3.0.3

Data: 2019-08-26
Commit: efcd020

- Opis karty pokazuje bazowe życie.
- Po użyciu przedmiotu bot informuje o zmianie relacji.

# 1.3.0.2

Data: 2019-08-26
Commit: 4158052

- Poprawki wewnętrzne.

# 1.3.0.1

Data: 2019-08-23
Commit: 7e5d40d

- Poprawki narzędzi administracji.

# 1.3.0.0

Data: 2019-08-23
Commit: 8805f1a

- Karta zapamiętuje swojego pierwszego właściciela.
- Bot zbiera statystyki użycia poleceń.
- Skorygowano szanse na przedmioty.

# 1.2.6.2

Data: 2019-08-22
Commit: 926da39

- Nowy styl profilu: karcianka, ze statystykami kart.
- Rzut monetą nie ma już limitu stawki.
- Profil zawiera link do konta na Shindenie.

# 1.2.6.1

Data: 2019-08-22
Commit: 7cd96f3

- Nowe polecenie życzenia widok (wishlist view): pozwala ukryć listę życzeń przed innymi.
- Nowe określenia karmy.
- Skorygowano wpływ karmy i zawartość pakietu kart.

# 1.2.6.0

Data: 2019-08-22
Commit: d155d0b

- Lista pakietów grupuje pakiety o tej samej nazwie.

# 1.2.5.7

Data: 2019-08-21
Commit: cf22e5d

- Sklepik jest dostępny dopiero od określonego poziomu.

# 1.2.5.6

Data: 2019-08-21
Commit: dbe8c78

- Obrazki kart odświeżają się częściej.

# 1.2.5.5

Data: 2019-08-21
Commit: cd0a3cd

- Własny obrazek można ustawić tylko karcie, która ma już główny obrazek.

# 1.2.5.4

Data: 2019-08-21
Commit: d71d35f

- Lista życzeń przychodzi w prywatnej wiadomości.

# 1.2.5.3

Data: 2019-08-21
Commit: 599f7ff

- Poprawki narzędzi administracji.

# 1.2.5.2

Data: 2019-08-21
Commit: 6d899fc

- Safari pojawia się częściej na kanałach bez doświadczenia.

# 1.2.5.1

Data: 2019-08-21
Commit: de5e744

- Bot zapisuje też edytowane wiadomości.

# 1.2.5.0

Data: 2019-08-21
Commit: c6bef5d

- Nowy przedmiot: skalpel, który pozwala ustawić karcie własny obrazek.
- Ulepszenie karty do SSS daje skalpel.
- Skorygowano ceny w sklepie.

# 1.2.4.4

Data: 2019-08-21
Commit: 55a7ef6

- Listę kart można przefiltrować na karty z obrazkiem, bez obrazka i z własnym obrazkiem.

# 1.2.4.3

Data: 2019-08-21
Commit: cb8dd68

- Poprawki wewnętrzne.

# 1.2.4.2

Data: 2019-08-20
Commit: 98083a9

- Lista właścicieli postaci obejmuje graczy ze wszystkich serwerów.

# 1.2.4.1

Data: 2019-08-20
Commit: 10bdea0

- Poprawki narzędzi administracji.

# 1.2.4.0

Data: 2019-08-20
Commit: 80f61de

- Lista właścicieli postaci pokazuje też karty osób spoza serwera.

# 1.2.3.5

Data: 2019-08-20
Commit: f477192

- Nowe polecenie aktualizuj (update): pobiera aktualne dane karty z Shindena.
- Wiadomość można oznaczyć do zrobienia na innym serwerze.

# 1.2.3.4

Data: 2019-08-19
Commit: b6768b3

- Poprawki wewnętrzne.

# 1.2.3.3

Data: 2019-08-19
Commit: c149c23

- Kasowanie zbyt starych wiadomości kończy się komunikatem zamiast błędu.

# 1.2.3.2

Data: 2019-08-19
Commit: 35dff4f

- Poprawiono uprawnienia do wyciszania.

# 1.2.3.1

Data: 2019-08-19
Commit: b467ca3

- Moderatorzy z odpowiednimi uprawnieniami Discorda mogą używać poleceń moderacji.

# 1.2.3.0

Data: 2019-08-19
Commit: b8921a5

- Nowa rola waifu: bot oznacza ją przy safari i loterii kart.

# 1.2.2.5

Data: 2019-08-17
Commit: 7f21374

- Wynik loterii kart pokazuje wygrane karty.

# 1.2.2.4

Data: 2019-08-17
Commit: dfa345f

- Nowe polecenie dla administracji rozdaj: loteria kart.

# 1.2.2.3

Data: 2019-08-17
Commit: ffbd193

- Polecenie chce muta wymaga potwierdzenia.

# 1.2.2.2

Data: 2019-08-17
Commit: 08be90e

- Nowe polecenie chce muta (mute me): bot wycisza cię na losowy czas.
- Zmiana waifu jeszcze mocniej obniża relację z poprzednimi.

# 1.2.2.1

Data: 2019-08-16
Commit: ec630f0

- Nie można ustawić jako waifu tej samej postaci drugi raz.

# 1.2.2.0

Data: 2019-08-16
Commit: ccf22a7

- Nowy przedmiot: kryształowa kula, która pokazuje dokładną relację z kartą.
- Nowe polecenie wymień na kule (crystal).
- Długa lista kart w wymianie jest skracana.
- Wymiana obniża relację wymienianych kart.

# 1.2.1.2

Data: 2019-08-15
Commit: c0cb819

- Nowe polecenia dla administracji: prefix, tchaos i tsup.
- Dane serwera są usuwane, gdy bot go opuści.

# 1.2.1.1

Data: 2019-08-15
Commit: c03a856

- Poprawiono tryb chaosu.

# 1.2.1.0

Data: 2019-08-15
Commit: 6964528

- Nowy tryb chaosu: bot losowo zamienia ludziom nicki.
- Każdy serwer może mieć własny prefiks i własne ustawienia nadzoru.

# 1.2.0.0

Data: 2019-08-13
Commit: 0373ec8

- Bot zbiera statystyki aktywności.
- Bot może zostać wyłączony na wybranych serwerach.

# 1.1.5.2

Data: 2019-08-13
Commit: c7904de

- Poprawki wewnętrzne.

# 1.1.5.1

Data: 2019-08-12
Commit: 6166ec4

- Statystyki pokazują otwarte pakiety.
- Raport działa też dla wiadomości z samym załącznikiem.

# 1.1.5.0

Data: 2019-08-12
Commit: 4c5ff2f

- Polecenie poświęć przyjmuje kilka kart naraz.
- Poprawiono sprawdzanie nicku przez administrację.

# 1.1.4.6

Data: 2019-08-11
Commit: 000993a

- Poprawki wewnętrzne.

# 1.1.4.5

Data: 2019-08-10
Commit: 582078c

- Skorygowano ceny i szanse na przedmioty.

# 1.1.4.4

Data: 2019-08-10
Commit: f6660de

- Pakiet z tytułu można kupić tylko dla tytułu z wystarczającą liczbą postaci.
- Skorygowano ceny w sklepie i koszt wyzwolenia karty.

# 1.1.4.3

Data: 2019-08-09
Commit: 0b4c36c

- Kupienie tego samego koloru przedłuża go zamiast kasować.
- Skorygowano ceny kolorów.
- Awatar na profilu ma obwódkę w kolorze rangi.

# 1.1.4.2

Data: 2019-08-08
Commit: 61817dc

- Skorygowano moc kart o specjalnych charakterach.
- Atak karty ma górny limit.
- Polecenie na życzeniach działa też dla innego gracza.

# 1.1.4.1

Data: 2019-08-08
Commit: 5057df3

- Listę kart można przefiltrować na karty w pogardzie i karty niewymienialne.

# 1.1.4.0

Data: 2019-08-08
Commit: 97a8cc9

- Nowe zasady pojedynków: talia musi mieć trzy karty i nie może być zbyt silna.
- Lista talii pokazuje moc kart i całej talii.

# 1.1.3.7

Data: 2019-08-08
Commit: ddbcac0

- Nowe polecenie oznacz podmień (tag replace): zamienia albo usuwa tag na wszystkich kartach.

# 1.1.3.6

Data: 2019-08-07
Commit: 8e8570c

- Bot nie ogłasza awansu za wiadomości z kanałów bez doświadczenia.

# 1.1.3.5

Data: 2019-08-06
Commit: 47f4a21

- Nowy skrót polecenia uwolnij: puśmje.
- Poprawiono polecenie na życzeniach.

# 1.1.3.4

Data: 2019-08-04
Commit: 4abd9c1

- Poprawki wewnętrzne.

# 1.1.3.3

Data: 2019-08-04
Commit: ddc0c2d

- Nowe polecenie karta+ (free card): darmowa karta raz na dobę.
- Karty w klatce i oznaczone jako ulubione są chronione przed uwolnieniem, zniszczeniem i poświęceniem.

# 1.1.3.2

Data: 2019-08-03
Commit: 53e050e

- Nowe polecenie na życzeniach (on wishlist): pokazuje, co masz na liście życzeń.
- Nowe polecenie oznacz puste (tag empty): oznacza wszystkie karty bez tagu.
- Profil pokazuje TC.

# 1.1.3.1

Data: 2019-08-01
Commit: 13f413a

- Nowe polecenie widok waifu: włącza i wyłącza kartę waifu na profilu.

# 1.1.3.0

Data: 2019-07-31
Commit: 8f4a3da

- Nowe polecenie czarny rynek (black market) dla graczy ze złą karmą.
- Walki na arenie i z botami wybierają kartę po jej numerze.

# 1.1.2.3

Data: 2019-07-31
Commit: 99f12d8

- Nowe topki: najsilniejsza karta, najwyższa karma i najniższa karma.
- Moc kart liczy się z ich statystyk.

# 1.1.2.2

Data: 2019-07-30
Commit: fb8ada0

- Na rynku można zdobyć więcej przedmiotów, zależnie od relacji karty i karmy.

# 1.1.2.1

Data: 2019-07-30
Commit: 7bfbcf9

- Nowe polecenie rynek (market): karta idzie na rynek i przynosi przedmioty, raz na kilka godzin zależnie od karmy.
- Wyszukiwanie z listy życzeń i ulubionych domyślnie pomija karty oznaczone jako ulubione.

# 1.1.2.0

Data: 2019-07-30
Commit: 1e4cca1

- Polecenia uwolnij i zniszcz działają na kilka kart naraz.
- Nowe polecenie poświęćm (killm): poświęca kilka kart naraz na rzecz jednej.
- Zmieniono losowanie na automacie.

# 1.1.1.0

Data: 2019-07-19
Commit: 63acc88

- Administracja może włączyć wydarzenie z wybraną pulą postaci.

# 1.1.0.8

Data: 2019-07-18
Commit: 62b3cfe

- W wymianie można dodać kilka kart naraz.

# 1.1.0.7

Data: 2019-07-18
Commit: 984a2be

- Gracz bez awatara dostaje domyślny obrazek.

# 1.1.0.6

Data: 2019-07-18
Commit: 0988ae0

- Nie można podarować SC samemu sobie.

# 1.1.0.5

Data: 2019-07-18
Commit: 2f08b08

- Nowe polecenie podarujsc (donatesc): przekazanie SC innemu graczowi, pomniejszone o podatek.

# 1.1.0.4

Data: 2019-07-17
Commit: 465876e

- Przemiana karty wymaga bardzo wysokiej relacji.
- Statystyki liczą przemiany oraz pakiety za aktywność.

# 1.1.0.3

Data: 2019-07-17
Commit: c6cb85d

- Poprawki dla strony.

# 1.1.0.2

Data: 2019-07-17
Commit: b25c26f

- Strona widzi listę życzeń gracza.
- Prosty widok karty pokazuje numer postaci.

# 1.1.0.1

Data: 2019-07-17
Commit: 5156f04

- Więcej tytułów zależnych od karmy.
- W wymianie nie można oddać karty Yato, karty Yami graczowi z dobrą karmą ani Raito graczowi ze złą.

# 1.1.0.0

Data: 2019-07-17
Commit: f675852

- Dobro i zło: gracze mają tytuły zależne od karmy.
- Nowe polecenie ofiaruj: za trzy krople krwi zmienia kartę w anioła (Raito) albo demona (Yami), a anioła lub demona w Yato.
- Statystyki liczą otwarte pakiety.

# 1.0.8.2

Data: 2019-07-15
Commit: 057ccd3

- Poprawiono działanie charakteru Yato.

# 1.0.8.1

Data: 2019-07-15
Commit: 203c88e

- Nowy charakter Yato, a charaktery mają też odporności.

# 1.0.8.0

Data: 2019-07-15
Commit: 2a68289

- Przebudowano charaktery kart: każdy ma słabości w walce, a doszły nowe charaktery Yami i Raito.

# 1.0.7.9

Data: 2019-07-15
Commit: a43532a

- Nowe polecenie uwolnij (release): uwalnia kartę i poprawia karmę.

# 1.0.7.8

Data: 2019-07-13
Commit: 75181da

- Postać z listy życzeń znika z niej po zdobyciu jej karty.
- Profil pokazuje kartę waifu.
- Administracja może dodać gracza do czarnej listy.

# 1.0.7.7

Data: 2019-07-11
Commit: 61bd973

- Lista życzeń pomija postacie, które już masz, i może ukryć karty oznaczone jako ulubione.

# 1.0.7.6

Data: 2019-07-11
Commit: 04d1083

- Nowe polecenia żdodaj (wadd) i żusuń (wremove): dodawanie i usuwanie kart, postaci i tytułów z listy życzeń.
- Statystyki liczą zniszczone i wyzwolone karty.

# 1.0.7.5

Data: 2019-07-11
Commit: b033482

- Karta z listy życzeń znika z niej po otrzymaniu jej w wymianie.

# 1.0.7.4

Data: 2019-07-11
Commit: cea86b4

- Nowe polecenie życzenia (wishlist): lista życzeń i karty z niej u innych graczy.
- Nowa ikonka 📝 dla kart z tagiem rezerwacja.

# 1.0.7.3

Data: 2019-07-10
Commit: b857fa0

- Skorygowano zmiany karmy i jej wpływ na relację.

# 1.0.7.2

Data: 2019-07-09
Commit: c17715c

- Prefiks poleceń działa bez względu na wielkość liter.

# 1.0.7.1

Data: 2019-07-09
Commit: 46b1bdf

- Nowe polecenie wyzwól (unleash): zamienia kartę niewymienialną na wymienialną za CT.
- Karma wpływa na relację nowych kart.

# 1.0.7.0

Data: 2019-07-08
Commit: 5838f05

- Nowa karma i waluta CT: zniszczenie karty daje CT, a działania w grze zmieniają karmę.
- Gracz, który opuścił wszystkie serwery, traci profil.

# 1.0.6.8

Data: 2019-07-07
Commit: 23daa27

- Prosty widok karty pokazuje właściciela, tytuł, relację i tagi.

# 1.0.6.7

Data: 2019-07-07
Commit: 333a740

- Nowe polecenie ulubione (favs): pokazuje, kto ma karty postaci z twojej listy ulubionych na Shindenie.

# 1.0.6.6

Data: 2019-07-06
Commit: eb47248

- Nowe polecenie karta- (card-): pokazuje kartę w prostej, tekstowej postaci.

# 1.0.6.5

Data: 2019-07-04
Commit: 641adc2

- Nowe ikonki: 💔 karta w pogardzie i 🔒 karta w klatce.

# 1.0.6.4

Data: 2019-07-04
Commit: 55c8c6f

- Listę kart można przefiltrować na karty bez danego tagu.
- Wyniki wyszukiwania kart mają ikonki: ⛔ niewymienialna, 💗 ulubiona, 🔄 do wymiany.

# 1.0.6.3

Data: 2019-07-03
Commit: 2e3f956

- Strona może dodawać karty do talii i z niej wyjmować.

# 1.0.6.2

Data: 2019-07-03
Commit: f6406aa

- Administracja może usuwać role z listy do samodzielnego nadania.
- Tag może składać się z kilku słów.

# 1.0.6.1

Data: 2019-07-03
Commit: 35271f9

- Nowe polecenie tag (oznacz): dodaje karcie tag albo go kasuje.
- Listę kart można przefiltrować po tagu.

# 1.0.6.0

Data: 2019-07-03
Commit: 197c776

- Opis karty pokazuje jej tagi.

# 1.0.5.5

Data: 2019-07-02
Commit: 9901a84

- Nowe polecenie loteria dla administracji: losuje zwycięzcę spośród osób, które dodały reakcję.

# 1.0.5.4

Data: 2019-07-01
Commit: 9de1dbc

- Skorygowano pakiety za aktywność.
- Safari lepiej radzi sobie z błędami.

# 1.0.5.3

Data: 2019-06-26
Commit: 8bfe473

- Poprawki dla strony.

# 1.0.5.2

Data: 2019-06-26
Commit: e56d400

- Obrazki kart odświeżają się po zmianach.

# 1.0.5.1

Data: 2019-06-26
Commit: 17696e6

- Nazwy postaci w wynikach polecenia jakie są linkami.

# 1.0.5.0

Data: 2019-06-25
Commit: fe893c8

- Nowe polecenie dla administracji check: sprawdza globalne emotki, kolor i nick gracza.

# 1.0.4.9

Data: 2019-06-25
Commit: 3cf7f49

- Statystyki znów pokazują liczbę poleceń.
- Nowy skrót polecenia raportu: zgłoś.

# 1.0.4.8

Data: 2019-06-24
Commit: 23714cb

- Poprawiono pakiety dodawane przez stronę.

# 1.0.4.7

Data: 2019-06-24
Commit: fa3e47c

- Drobna poprawka liczenia wiadomości.

# 1.0.4.6

Data: 2019-06-24
Commit: d9a9a22

- Poprawiono liczenie wiadomości na kanałach bez doświadczenia.
- Profil zakłada się przy pierwszej wiadomości.

# 1.0.4.5

Data: 2019-06-22
Commit: 2af1ad7

- Nowe polecenie zniszcz (destroy): niszczy kartę.
- Ulepszanie nie przyjmuje dwa razy tej samej karty.

# 1.0.4.4

Data: 2019-06-22
Commit: 3c4d1f2

- Statystyki pokazują wydane TC oraz poświęcone i ulepszone karty.

# 1.0.4.3

Data: 2019-06-20
Commit: ae0c713

- Kropla krwi na karcie o dobrej relacji może zamiast tego poprawić relację.
- Skorygowano szanse na przedmioty i zasady pojedynków.

# 1.0.4.2

Data: 2019-06-20
Commit: 4bb79a8

- Nowy przedmiot: kropla twojej krwi, która zwiększa liczbę ulepszeń karty o bardzo wysokiej relacji.
- Restart karty zeruje jej relację.
- Pierścionek wymaga co najmniej relacji „Miłość”.

# 1.0.4.1

Data: 2019-06-19
Commit: 96cd210

- Walki z botami liczą się do statystyk areny karty.
- Poprawiono styl profilu z obrazkiem.

# 1.0.4.0

Data: 2019-06-18
Commit: 940b8d6

- Poświęcenie karty tej samej postaci daje więcej doświadczenia przy ulepszaniu.
- Skorygowano szanse na przedmioty i ceny w sklepie.

# 1.0.3.10

Data: 2019-06-17
Commit: 866e0a5

- Wycofano automatyczne zakładanie profilu po otrzymaniu roli.

# 1.0.3.9

Data: 2019-06-17
Commit: 42b95c8

- Wygrana walka z botami może dać więcej przedmiotów.
- Skorygowano liczbę ulepszeń potrzebną do awansu z SS na SSS.

# 1.0.3.8

Data: 2019-06-15
Commit: 0e6db59

- Nowa topka: moc kart.
- Poprawiono topki wiadomości z miesiąca.
- Polecenie jakie pokazuje właścicieli kart i dzieli długie wyniki na kilka wiadomości.
- Poprawiono wyciszanie moderatorów.

# 1.0.3.7

Data: 2019-06-10
Commit: 9819fd2

- Nowy przedmiot: wielka fontanna czekolady, która mocno poprawia relację.
- Wynik pojedynku zależy też od życia kart.
- Skorygowano szanse na przedmioty i ceny w sklepie.

# 1.0.3.6

Data: 2019-06-10
Commit: 2d1b946

- Poprawiono obrazek remisu na arenie.

# 1.0.3.5

Data: 2019-06-09
Commit: dfc24c9

- Skorygowano próg relacji potrzebny do walki z botami.
- Wynik walki z botami pokazuje twoją kartę.

# 1.0.3.4

Data: 2019-06-08
Commit: 7101669

- Nowy, najniższy poziom relacji: pogarda. Karty w pogardzie nie można poświęcić ani wymienić.
- Karta ze zbyt niską relacją nie może walczyć z botami.
- Nowy skrót polecenia raportu: report.

# 1.0.3.3

Data: 2019-06-08
Commit: a273ab4

- Skorygowano nagrody z walk z botami.

# 1.0.3.2

Data: 2019-06-07
Commit: db4f2ee

- Doświadczenie z walki z botami zależy od liczby zadanych ciosów, a przedmioty mogą wypaść nawet po przegranej.

# 1.0.3.1

Data: 2019-06-07
Commit: e3710f8

- Walka z botami daje nagrody: doświadczenie, przedmioty i czasem kartę, ale obniża relację.

# 1.0.3.0

Data: 2019-06-07
Commit: e4c85a3

- Nowe polecenie arenam (wildm): walka twojej karty z kartami bota w stylu GMwK.
- Statystyki można sprawdzić też innemu graczowi; pokazują liczbę wiadomości i poleceń.
- Skorygowano szanse na przedmioty.

# 1.0.2.12

Data: 2019-06-04
Commit: 45c0933

- Poprawiono życie kart w GMwK.

# 1.0.2.11

Data: 2019-06-04
Commit: 520c39a

- Role za poziom są nadawane od razu po awansie.

# 1.0.2.10

Data: 2019-06-04
Commit: 870b28a

- Poprawiono pasek doświadczenia na profilu.

# 1.0.2.9

Data: 2019-06-04
Commit: 0654696

- Restarty dają karcie dodatkowy atak i obronę, a opis karty pokazuje ich liczbę.
- Skorygowano pakiety za aktywność.

# 1.0.2.8

Data: 2019-06-03
Commit: 7b2a648

- Nowe polecenie reset (restart): zamienia kartę SSS z powrotem w kartę E ze stałym bonusem.

# 1.0.2.7

Data: 2019-06-03
Commit: f9c08b4

- Nowe polecenie jakie (which): pokazuje, kto ma karty z danego tytułu.
- Zmiana waifu mocniej obniża relację z poprzednimi.

# 1.0.2.6

Data: 2019-06-01
Commit: 3744eca

- Nowe pakiety w sklepie: pomarańczowy, złoty i różowy, z gwarantowaną kartą wyższej rangi.
- Pakiety zawierają więcej kart.
- Skorygowano ceny w sklepie, statystyki kart SSS, relację i pakiety za aktywność.

# 1.0.2.5

Data: 2019-05-31
Commit: 6f821b1

- Skorygowano szanse na karty rangi D i na przedmioty.

# 1.0.2.4

Data: 2019-05-31
Commit: e89dd9d

- Poprawki wewnętrzne.

# 1.0.2.3

Data: 2019-05-30
Commit: ac82003

- Nowe polecenie zdejmij role (remove role): zdejmuje samodzielnie nadaną rolę.

# 1.0.2.2

Data: 2019-05-30
Commit: efab710

- Poprawki wewnętrzne.

# 1.0.2.1

Data: 2019-05-30
Commit: ea353f1

- Pojedynek i GMwK mogą zakończyć się remisem.

# 1.0.2.0

Data: 2019-05-30
Commit: 6392f43

- Poprawki wewnętrzne.

# 1.0.1.15

Data: 2019-05-29
Commit: 7b943b2

- Nowe polecenia przyznaj role i wypisz role: samodzielne nadawanie ról z listy serwera.

# 1.0.1.14

Data: 2019-05-29
Commit: 3e3b345

- Przedmioty i odpowiedzi w zagadkach mają stałą kolejność.

# 1.0.1.13

Data: 2019-05-29
Commit: 90c4d46

- Safari i GMwK nie zawieszają się już po błędzie.

# 1.0.1.12

Data: 2019-05-29
Commit: 5213c3b

- Poprawiono przewijanie topki.

# 1.0.1.11

Data: 2019-05-29
Commit: 97ef1cd

- Safari pokazuje godzinę końca polowania zamiast odliczania.

# 1.0.1.10

Data: 2019-05-29
Commit: 229db4b

- Poprawki wewnętrzne.

# 1.0.1.9

Data: 2019-05-29
Commit: 676f501

- Naprawiono wysyłanie wiadomości z panelu na PW.

# 1.0.1.8

Data: 2019-05-28
Commit: 29af8f9

- Zapisy do GMwK mają własną emotkę, a ogłoszenie pokazuje najwyższą dozwoloną jakość karty.
- Poprawki kolejki wykonywania poleceń.

# 1.0.1.7

Data: 2019-05-28
Commit: 52802a1

- Poprawki kolejki wykonywania poleceń.

# 1.0.1.6

Data: 2019-05-28
Commit: 9379f0f

- Poprawki kolejki wykonywania poleceń.

# 1.0.1.5

Data: 2019-05-28
Commit: 6c213f9

- Poprawki kolejki wykonywania poleceń.

# 1.0.1.4

Data: 2019-05-28
Commit: afd9ff0

- Poprawki kolejki wykonywania poleceń.

# 1.0.1.3

Data: 2019-05-28
Commit: c619f92

- Życie karty uwzględnia karę za słabą relację i ma górny limit.

# 1.0.1.2

Data: 2019-05-28
Commit: 3979299

- Poprawki kolejki wykonywania poleceń.

# 1.0.1.1

Data: 2019-05-28
Commit: 13f1133

- Poprawki wewnętrzne.

# 1.0.1.0

Data: 2019-05-28
Commit: fcb30d1

- Nowe polecenie masakra (massacre): Grupowa Masakra w Kisielu, czyli walka kart wielu graczy naraz.
- Nowe polecenie ban: czasowy ban dla administracji.
- Skorygowano statystyki kart SSS.

# 1.0.0.9

Data: 2019-05-28
Commit: 9467ea3

- Naprawiono liczenie wygranej na automacie.

# 1.0.0.8

Data: 2019-05-28
Commit: 8058a81

- Listę kart można sortować także według życia.
- Poprawki dla strony.

# 1.0.0.7

Data: 2019-05-27
Commit: 4480c49

- Kara nałożona z raportu ma powód z tego raportu.
- Safari lepiej radzi sobie z błędami Discorda.

# 1.0.0.6

Data: 2019-05-27
Commit: cac365a

- Liczba kart pokazuje też karty SSS.
- Pokonana karta w pojedynku jest szara.

# 1.0.0.5

Data: 2019-05-27
Commit: 90a0910

- Listę kart można sortować według jakości, ataku, obrony i relacji albo pokazać tylko karty z klatki.
- Portfel pokazuje też portfel innego gracza.
- Poprawiono kolejność nadawania ról za poziom.

# 1.0.0.4

Data: 2019-05-27
Commit: 19aa9df

- Ochrona przed spamem pomija kanały wyłączone z nadzoru.
- Ustawienia automatu mają polskie nazwy (stawka, mnożnik, rzędy).

# 1.0.0.3

Data: 2019-05-27
Commit: 185751a

- Poprawiono położenie postaci na obrazku safari.

# 1.0.0.2

Data: 2019-05-27
Commit: c5b5667

- Poprawiono kupowanie koloru i globalnych emotek.

# 1.0.0.1

Data: 2019-05-27
Commit: 610bd14

- Strona dostała dostęp do kart graczy.
- Poprawiono pasek doświadczenia na profilu.

# 1.0.0.0

Data: 2019-05-25
Commit: 2423200

- Pierwsze wydanie nowego Sanakana.
- Moderacja: wyciszenia, raporty, role do samodzielnego nadania, krainy, powitania i pożegnania.
- Poziomy i doświadczenie za wiadomości, profil, portfel i topki.
- Wyszukiwanie anime, mang i postaci na Shindenie oraz nowe odcinki.
- Zabawy: automat, rzut monetą i zagadki.
- Gra karciana: pakiety, ulepszanie, klatka, talia, wymiana, pojedynki, sklep i safari.
- Ochrona przed spamem.

# 1.0.0.0-alpha

Data: 2019-04-25
Commit: de12b45

- Początek prac nad nowym Sanakanem.
