<?php  

require_once 'AppController.php';
require_once __DIR__.'/../repository/UserRepository.php';
require_once __DIR__.'/../repository/CardsRepository.php';

class DashboardController extends AppController
{
    private CardsRepository $cardsRepository;

    private static $instance = null;

    private function __construct() {
        $this->cardsRepository = new CardsRepository();
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function index(?int $id = null)
    {
        // TODO wyswietli wszystkie projekty z bazy danych
        $cards = [
            [
                'id' => 1,
                'title' => 'Ace of Spades',
                'subtitle' => 'Legendary card',
                'imageUrlPath' => 'https://deckofcardsapi.com/static/img/AS.png',
                'href' => '/cards/ace-of-spades'
            ],
            [
                'id' => 2,
                'title' => 'Queen of Hearts',
                'subtitle' => 'Classic romance',
                'imageUrlPath' => 'https://deckofcardsapi.com/static/img/QH.png',
                'href' => '/cards/queen-of-hearts'
            ],
            [
                'id' => 3,
                'title' => 'King of Clubs',
                'subtitle' => 'Royal strength',
                'imageUrlPath' => 'https://deckofcardsapi.com/static/img/KC.png',
                'href' => '/cards/king-of-clubs'
            ],
            [
                'id' => 4,
                'title' => 'Jack of Diamonds',
                'subtitle' => 'Sly and sharp',
                'imageUrlPath' => 'https://deckofcardsapi.com/static/img/JD.png',
                'href' => '/cards/jack-of-diamonds'
            ],
            [
                'id' => 5,
                'title' => 'Ten of Hearts',
                'subtitle' => 'Lucky draw',
                'imageUrlPath' => 'https://deckofcardsapi.com/static/img/0H.png',
                'href' => '/cards/ten-of-hearts'
            ],
        ];

        if ($id>0) {
            $cards = [
                [
                    'id' => 1,
                    'title' => 'Ace of Spades',
                    'subtitle' => 'Legendary card',
                    'imageUrlPath' => 'https://deckofcardsapi.com/static/img/AS.png',
                    'href' => '/cards/ace-of-spades'
                ],
            ];
        }

        $userRepository = new UserRepository();
        $users = $userRepository->getUsers();

        var_dump($users);

        return $this->render("dashboard", ['cards' => $cards, 'users' => $users]);
    }

    public function search(){
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        header('Content-Type: application/json');

        if (!$this->isPost){
            http_response_code(405);
            echo json_encode(["status" => "405", "message" => "Method Not Allowed"]);
            return;
        }

        if ('Content-Type: application/json'){
            http_response_code(415);
            echo json_encode(["status" => "415", "message" => "Unsupported Media Type"]);
            return;
        }

        $content = trim(file_get_contents("php://input"));
        $decoded = json_decode($content, true);

        http_response_code(200);
        $cards = $this->cardsRepository->getCardsByTitle('heart');
        echo json_encode($cards);
    }
}
