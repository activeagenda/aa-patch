DELETE FROM codt WHERE CodeTypeID IN 
(1,2,7,10,124,138,170,194,199,261,272,281,300,301,302,303,304,305,306,307,308,309,401,402,403,999);
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('1','Prawa Podglądu','Opisowe nazwy dla praw podglądu','1','1','2005-05-24 18:02:08','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('2','Prawa Edycji','Opisowe nazwy dla praw edycji','1','1','2005-05-24 18:08:13','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('7','Zasięg organizacji',NULL,'0','0','0000-00-00 00:00:00','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('10','Bool Tak/Nie',NULL,'0','1','2005-07-01 14:24:35','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('124','Kategoria kosztu',NULL,'0','3','2005-03-04 16:45:03','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('138','Język użytkownika','Preferowany język użytkownika','0','1','2005-03-29 15:01:49','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('170','Charakter uczestnictwa','Krótkie frazy do opisu charakteru uczestnictwa','0','3','2005-05-05 14:48:46','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('194','Status dokumentacji','Status opracowania dokumentacji wspierającej','0','3','2006-03-21 11:32:14','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('199','Status udziału','Status osoby odpowiedzialnej lub uczestniczącej','0','3','2007-01-05 12:49:25','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('261','Status działania',NULL,'0','0','0000-00-00 00:00:00','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('272','Status modułu',NULL,'0','1','2004-11-12 09:46:57','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('281','Opis daty','Krótkie frazy do opisu pola daty w module zdalnym Daty','0','3','2005-04-20 16:58:13','0');

INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('300','Bool Graficzne','Bool Graficzne true/false','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('301','Status graficznie tak/nie','Status graficznie tak/nie','0','3','2007-11-01 13:46:23','0');

INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('302','Typy logów zdarzeń użytkownika','Typy wpisów logów użytkownika','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('303','Status współpracy z organizacją','Czy osoba aktualnie pracuje w organizacji','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('304','Miesiące','Lista miesięcy z numerami','0','3','2011-09-14 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('305','Miesiące','Lista miesięcy','0','3','2011-09-14 13:46:23','0');			
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('306','Action state','Stan w którym jest działanie','0','3','2007-11-01 13:46:23','0');			
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('307','Action state command','Polecenia zmainy stanu działania','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('308','Locations','Funkcje lokalizacji','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('309','Periods','Częstotliwość wysyłania','0','3','2007-11-01 13:46:23','0');			

INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('401','Maskulinum Status','Status elementu rodzaju męskiego','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('402','Neutrum Status','Status elementu rodzaju nijakiego','0','3','2007-11-01 13:46:23','0');
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('403','Feminum Status','Status elementu rodzaju żeńskiego','0','3','2007-11-01 13:46:23','0');
		
			
INSERT INTO codt (CodeTypeID,Name,Description,UseValue,_ModBy,_ModDate,_Deleted)
            VALUES ('999','Granica zastrzeżonych kodów','Granica zastrzeżonej numeracji kodów','0','3','2007-11-01 13:46:23','0');


DELETE FROM cod WHERE CodeTypeID IN 
(1,2,7,10,124,138,170,194,199,261,272,281,300,301,302,303,304,305,306,307,308,309,401,402,403,999);
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('1','0','10','0','Podgląd -> Brak','1','2005-05-24 18:11:56','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('1','1','20','1','Podgląd -> Organizacje','1','2005-05-24 18:06:18','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('1','2','30','2','Podgląd -> Wszyscy','1','2005-05-24 18:02:40','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('2','0','10','0','Edycja -> Brak','1','2005-05-24 18:10:59','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('2','1','20','1','Edycja -> Organizacje','1','2005-05-24 18:09:23','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('2','2','30','2','Edycja -> Wszyscy','1','2005-05-24 18:08:55','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','2',NULL,NULL,'Międzynarodowy','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','4',NULL,NULL,'Narodowy','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','5',NULL,NULL,'Wojewódzki','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','6',NULL,NULL,'Miejscowość','1','2007-04-27 17:59:43','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','7',NULL,NULL,'Powiat','3','2006-05-30 12:57:25','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','8',NULL,NULL,'Gmina','3','2006-05-31 08:47:56','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('7','9',NULL,NULL,'Regionalny','1','2007-04-27 17:59:58','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('10','-1','20','0','Nie','1','2005-05-26 14:44:23','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('10','1','10','1','Tak','1','2005-05-26 14:42:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','1','0',NULL,'Prowizja','3','2005-03-04 16:46:58','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','2','0',NULL,'Strata','3','2005-03-04 16:46:38','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','3','0',NULL,'Premia','3','2005-03-05 16:28:32','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','4','0',NULL,'Praca','3','2005-09-21 09:56:09','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','5','0',NULL,'Usługi','3','2005-03-04 16:49:46','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('124','6','0',NULL,'Opłaty','3','2005-03-05 16:34:51','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('138','1',NULL,'en_US','English (US)','1','2007-04-13 07:51:13','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('138','5',NULL,'pl_PL','Polski (PL)','1','2007-04-13 07:50:49','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','1',NULL,NULL,'Project manager','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','2',NULL,NULL,'Członek zespołu','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','3',NULL,NULL,'Wspomagający zespół','3','2005-05-05 15:30:09','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','4',NULL,NULL,'Etatowy pracownik','0','0000-00-00 00:00:00','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','5',NULL,NULL,'Wolny współpracownik','3','2009-01-08 11:10:50','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('194','1',NULL,NULL,'Brak dokumentacji','3','2006-03-21 11:32:40','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('194','2',NULL,NULL,'Trochę dokumentacji','3','2006-03-21 11:32:57','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('194','3',NULL,NULL,'Podstawowa dokumentacja','3','2006-03-21 11:33:22','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('194','4',NULL,NULL,'Istotna dokumentacja','3','2006-03-21 11:33:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('194','5',NULL,NULL,'Pełna dokumentacja','3','2006-03-21 11:33:50','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','1','10','10','Działanie w trakcie','3','2007-01-05 12:50:03','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','2','20','20','Działanie uśpione','3','2007-01-05 12:50:22','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','3','30','30','Ukończone','3','2007-01-05 12:52:23','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','4','40','40','Przerwane','3','2007-01-05 12:50:54','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','5','50','50','Do decyzji','3','2007-01-05 12:51:09','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','6','60','60','Nieznany','3','2007-01-05 12:51:23','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('199','7','25','25','Zdarzenie jednorazowe','3','2007-01-05 12:54:46','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('261','1','0','1','Do wykonania','3','2006-01-24 17:31:45','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('261','2','10',NULL,'W trakcie','1','2004-10-14 03:58:51','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('261','3','20',NULL,'Ukończone','1','2004-10-14 03:59:01','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('261','11','40',NULL,'Przerwane','1','2004-10-14 03:59:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','1','10','1','Nie wczytany','1','2004-11-16 11:14:38','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','2','60','6','Ukończony','1','2004-11-16 11:15:53','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','3','30','3','Tabele i pliki','1','2004-11-16 11:15:05','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','4','20','2','Tylko tabele','1','2004-11-16 11:14:51','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','5','40','4','Rozwój','1','2004-11-16 11:15:24','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('272','7','70','7','Zbędny','3','2005-01-27 11:47:26','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','1',NULL,NULL,'Do','3','2005-04-21 17:19:45','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','2',NULL,NULL,'Przydzielono','3','2005-04-21 17:19:54','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','3',NULL,NULL,'Ukończono','3','2005-04-21 17:20:28','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','4',NULL,NULL,'Stworzono','3','2005-04-21 17:20:44','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','5',NULL,NULL,'Skontaktowano','3','2009-01-08 11:15:30','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','6',NULL,NULL,'Ostęplowano','3','2005-04-21 17:46:51','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','7',NULL,NULL,'Wydarzyło się','3','2005-04-21 17:42:49','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','8',NULL,NULL,'Odwiedzono','3','2005-04-21 17:22:28','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','9',NULL,NULL,'Przeglądnięto','3','2005-04-21 17:22:52','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','10',NULL,NULL,'Wygasa','3','2005-04-21 17:23:38','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','11',NULL,NULL,'Opublikowano','3','2005-04-21 17:24:02','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','12',NULL,NULL,'Użyto','3','2005-04-21 17:24:16','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','13',NULL,NULL,'Przerwano','3','2005-04-21 17:24:56','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','14',NULL,NULL,'Tranzakcja','3','2005-04-21 17:13:57','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','15',NULL,NULL,'Status','3','2005-04-21 17:25:10','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','16',NULL,NULL,'Sytuacja','3','2005-04-21 17:46:40','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','17',NULL,NULL,'Effective','3','2005-04-22 10:11:31','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','18',NULL,NULL,'Koszty','3','2005-04-21 17:28:31','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','19',NULL,NULL,'Nagroda','3','2005-04-21 17:54:02','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','20',NULL,NULL,'Ostatni przegląd','3','2005-04-21 17:23:11','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','21',NULL,NULL,'Następny przegląd','3','2005-04-21 17:23:22','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','22',NULL,NULL,'Ewaluacja','3','2005-04-21 17:59:25','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','23',NULL,NULL,'Rozwiązanie','3','2005-04-21 17:50:25','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','24',NULL,NULL,'Zdarzenie','3','2005-04-21 16:56:48','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','25',NULL,NULL,'Kwestia','3','2005-04-21 17:30:51','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','26',NULL,NULL,'Feedback','3','2005-04-21 17:07:26','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','27',NULL,NULL,'Odpowiedź','3','2005-04-21 17:31:23','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','28',NULL,NULL,'Rozpoczęcie','3','2005-04-22 12:03:08','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','29',NULL,NULL,'Dyspozycja','3','2005-04-21 17:12:42','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','30',NULL,NULL,'Transfer','3','2005-04-21 17:33:34','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','31',NULL,NULL,'Wpis rekordu','3','2005-04-22 09:50:26','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','32',NULL,NULL,'Zraportowano','3','2005-04-22 09:52:41','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','33',NULL,NULL,'Zamknięcie','3','2005-04-22 09:55:07','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','34',NULL,NULL,'Powstały','3','2005-04-22 10:04:39','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','35',NULL,NULL,'Przegrano','3','2005-04-22 10:05:10','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','36',NULL,NULL,'Zakończenie','3','2005-04-22 10:11:41','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','37',NULL,NULL,'Sprawa','3','2005-04-22 10:45:17','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','38',NULL,NULL,'Spotkanie','3','2005-04-22 10:50:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','39',NULL,NULL,'Wniosek','3','2005-04-22 10:56:16','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','40',NULL,NULL,'Najęcie','3','2005-04-22 11:35:49','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','41',NULL,NULL,'Zerwanie','3','2005-04-22 11:36:54','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','42',NULL,NULL,'Kwalifikacja','3','2005-04-22 11:43:05','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','43',NULL,NULL,'Przedłożenie','3','2005-04-22 11:54:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','44',NULL,NULL,'Implementacja','3','2005-04-22 11:54:53','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','45',NULL,NULL,'Uczestncitow','3','2005-04-22 12:03:33','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','46',NULL,NULL,'Wsysłka','3','2005-04-22 18:32:27','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','47',NULL,NULL,'Wydano','3','2005-04-22 18:40:37','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','48',NULL,NULL,'Powiadomiono','3','2006-03-27 15:27:26','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','49',NULL,NULL,'Urodziny','3','2006-09-25 11:08:29','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','50',NULL,NULL,'Zapytanie','3','2006-12-29 12:28:25','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','51',NULL,NULL,'Harmonogram','3','2007-02-07 18:40:24','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','52',NULL,NULL,'Podpis','3','2007-04-23 15:57:20','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','53',NULL,NULL,'Przybycie','3','2007-04-23 18:04:34','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','54',NULL,NULL,'Odjazd','3','2007-04-23 18:04:50','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','55',NULL,NULL,'Zatwierdzenie','3','2007-06-22 12:39:22','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','56',NULL,NULL,'Ekspozycja','3','2007-07-30 16:37:48','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','57',NULL,NULL,'Ocena','3','2008-01-12 19:17:25','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('260','200',NULL,NULL,'Logistyk','3','2008-02-13 18:57:22','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','58',NULL,NULL,'Cel','3','2008-02-15 15:28:17','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('281','59',NULL,NULL,'Obiad','3','2008-02-18 19:30:40','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('260','201',NULL,NULL,'Porozumienie z drugą stroną','3','2008-02-21 16:24:18','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('260','203',NULL,NULL,'Zarządzający raportami','3','2009-01-17 08:05:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('260','204',NULL,NULL,'Odbiorca raportu','3','2009-01-17 10:50:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('260','205',NULL,NULL,'Składający raport','3','2009-01-17 13:51:52','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('170','99',NULL,NULL,'Pusty','3','2009-02-03 19:40:38','0');

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('300','1','1',NULL,'&#9679;','3','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('300','0','2',NULL,'&#9675;','3','2011-07-30 13:48:35','0');			
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('301','1','1',NULL,'&#9679;','3','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('301','2','2',NULL,'&#9675;','3','2011-07-30 13:48:35','0');

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('302','1',NULL,NULL,'Login','3','2007-11-01 13:47:02','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('302','2',NULL,NULL,'Logout','3','2007-11-01 13:47:14','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('302','3',NULL,NULL,'Pobranie strony','3','2007-11-01 13:47:27','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('302','9',NULL,NULL,'Nieudany login','3','2007-11-01 13:48:21','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('302','10',NULL,NULL,'Odrzucenie','3','2007-11-01 13:48:35','0');			
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('303','0','2',NULL,'Były współpracownik','3','2011-07-30 13:48:35','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('303','1','1',NULL,'Obecny współpracownik','3','2011-07-30 13:48:35','0');	
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','1',NULL,'31','-01- Styczeń','1','2011-07-30 13:48:35','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','2',NULL,'29','-02- Luty','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','3',NULL,'31','-03- Marzec','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','4',NULL,'30','-04- Kwiecień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','5',NULL,'31','-05- Maj','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','6',NULL,'30','-06- Czerwiec','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','7',NULL,'31','-07- Lipiec','1','2011-07-30 13:48:35','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','8',NULL,'31','-08- Sierpień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','9',NULL,'30','-09- Wrzesień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','10',NULL,'31','-10- Październik','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','11',NULL,'30','-11- Listopad','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('304','12',NULL,'31','-12- Grudzień','1','2011-07-30 13:48:35','0');

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','1', '1','31','Styczeń','1','2011-07-30 13:48:35','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','2','2','29','Luty','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','3','3','31','Marzec','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','4','4','30','Kwiecień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','5','5','31','Maj','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','6','6','30','Czerwiec','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','7','7','31','Lipiec','1','2011-07-30 13:48:35','0');			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','8','8','31','Sierpień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','9','9','30','Wrzesień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','10','10','31','Październik','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','11','11','30','Listopad','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('305','12','12','31','Grudzień','1','2011-07-30 13:48:35','0');	
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('306','1','5','1','<span style="color:#C60101"><img src="themes/aa_theme/img/status_nocall.png"/> Niezgłoszona</span>','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('306','2','1','2','<span style="color:#4384C5"><img src="themes/aa_theme/img/status_wait.png"/> Zgłoszona</span>','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('306','3','2','3','<span style="color:green"><img src="themes/aa_theme/img/status_allow.png"/> Zaakceptowana</span>','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('306','4','3','4','<span style="color:#C60101"><img src="themes/aa_theme/img/status_reject.png"/> Odrzucona</span>','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('306','5','4','5','<span style="color:#4384C5"><img src="themes/aa_theme/img/status_closed.png"/> Zamknięta</span>','1','2011-07-30 13:48:35','0');

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('307','1','5','1','Ponownie otwórz sprawę i edytuj rekord','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('307','2','1','2','Zgłoś sprawę do dalszego działania i zablokuj edycję rekordu','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('307','3','2','3','Zaakceptuj sprawę do dalszego działania','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('307','4','3','4','Odrzuć wykonanie sprawy &nbsp;&nbsp;(uzasadnij decyzję w uwadze jeżeli konieczne)','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('307','5','4','5','Zamknij sprawę jako załatwioną  &nbsp;&nbsp;(umieść dodatkowe informacje w uwadze jeżeli konieczne)','1','2011-07-30 13:48:35','0');								

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('308','1','1','1','Centrala','1','2011-07-30 13:48:35','0');				
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('308','2','2','2','Oddział','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('308','3','3','3','Filia','1','2011-07-30 13:48:35','0');		
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('308','4','4','4','Punkt','1','2011-07-30 13:48:35','0');					

INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('309','1','1','1','jeden raz w dzień','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('309','2','2','2','co tydzień od dnia','1','2011-07-30 13:48:35','0');		
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('309','3','3','3','co miesiąc od dnia','1','2011-07-30 13:48:35','0');
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('309','4','4','4','co roku od dnia','1','2011-07-30 13:48:35','0');			
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('401','1','1','1','otwarty','1','2011-07-30 13:48:35','0');				
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('401','2','2','2','zamknięty','1','2011-07-30 13:48:35','0');			
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('402','1','1','1','otwarte','1','2011-07-30 13:48:35','0');				
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('402','2','2','2','zamknięte','1','2011-07-30 13:48:35','0');			
			
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('403','1','1','1','otwarta','1','2011-07-30 13:48:35','0');				
INSERT INTO cod (CodeTypeID,CodeID,SortOrder,Value,Description,_ModBy,_ModDate,_Deleted)
            VALUES ('404','2','2','2','zamknięta','1','2011-07-30 13:48:35','0');				
			

