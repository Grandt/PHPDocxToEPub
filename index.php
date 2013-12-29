<?php
	error_reporting(E_ALL | E_STRICT);
	ini_set('error_reporting', E_ALL | E_STRICT);
	ini_set('display_errors', 1);

	//$path = "example/Mystery_Stories_for_Girls.docx";
	$path = "example/H1.docx";
 
	include_once './Docx2ePub.php';
	
	$parser = new Docx2ePub();
	$book = $parser->parse($path);
	
	$book->setTitle("Test book");
	$book->setIdentifier("http://JohnJaneDoePublications.com/books/" . $path, EPub::IDENTIFIER_URI); // Could also be the ISBN number, prefered for published books, or a UUID.
	$book->setLanguage("en"); // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
	$book->setDescription("This is a brief description\nA test ePub book as an example of building a book in PHP");
	$book->setAuthor("John Doe Johnson", "Johnson, John Doe");
	$book->setPublisher("John and Jane Doe Publications", "http://JohnJaneDoePublications.com/"); // I hope this is a non existant address :)
	$book->setDate(time()); // Strictly not needed as the book date defaults to time().
	$book->setRights("Copyright and licence information specific for the book."); // As this is generated, this _could_ contain the name or licence information of the user who purchased the book, if needed. If this is used that way, the identifier must also be made unique for the book.
	$book->setSourceURL("http://JohnJaneDoePublications.com/books/" . $path);

	$book->addDublinCoreMetadata(DublinCore::CONTRIBUTOR, "PHP");

	//echo $goodHTML;
	$book->finalize();
	$book->sendBook("H1.epub");
	