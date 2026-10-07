<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with('user', 'categories', 'images')->latest()->paginate(12);
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',

            'images' => 'nullable|array|max:5',
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif,avif,bmp', 'max:5120',],
        ]);

        $data['user_id'] = auth()->id();
        // Utilizzo un ciclo per controllare che lo slug sia unico, se non lo è aggiungo un numero progressivo
        $slug = Str::slug($data['name']);
        $originalSlug = $slug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $data['slug'] = $slug;
        // Separo le informazioni che non appartengono alla tabella products
        $categories = $data['categories'] ?? [];
        $images = $data['images'] ?? [];
        unset($data['categories'], $data['images']);
        // Creo l'oggetto product e lo salvo nel database
        $product = Product::create($data);
        // Associo le categorie al prodotto
        $product->categories()->sync($categories);
        foreach ($images as $image) {
            $path = $image->store('products', 'public');

            $product->images()->create([
                'path' => $path,
            ]);
        }

        $product->load(['user', 'images', 'categories']);
        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['user', 'categories', 'images']);
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Product $product, Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',

            'images' => 'nullable|array|max:5',
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp,gif,avif,bmp',
                'max:5120',
            ],

            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|distinct',
        ]);
        // Recupero categorie ed immagini separatamente
        $categories = $data['categories'] ?? [];
        $images = $data['images'] ?? [];
        $removeImages = $data['remove_images'] ?? [];

        unset($data['categories'], $data['images'], $data['remove_images']);
        // Controllo lo slug, se il nome è cambiato lo rigenero, inoltre devo escludere il prodotto che sta venendo modificato altrimenti verrebbe considerato come un duplicato 
        if ($data['name'] !== $product->name) {
            $slug = Str::slug($data['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (
                Product::where('slug', $slug)
                ->where('id', '!=', $product->id)
                ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $data['slug'] = $slug;
        }

        // Controllo che gli Id appartengano al prodotto
        foreach ($removeImages as $imageId) {
            if (!$product->images()->where('id', $imageId)->exists()) {
                return response()->json([
                    'message' => 'Una delle immagini da eliminare non appartiene a questo prodotto.'
                ], 422);
            }
        }

        // Controllo il numero finale delle immagini
        $currentCount = $product->images()->count();
        $removeCount = count($removeImages);
        $newCount = count($images);

        if ($currentCount - $removeCount + $newCount > 5) {
            return response()->json([
                'message' => 'Un prodotto non può avere più di 5 immagini.'
            ], 422);
        }

        // Elimino le immagini selezionate
        foreach ($removeImages as $imageId) {
            $image = $product->images()->findOrFail($imageId);
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
        
        $product->update($data);
        $product->categories()->sync($categories);
        // Carico le nuove immagini

        foreach ($images as $image) {
            $path = $image->store('products', 'public');

            $product->images()->create([
                'path' => $path,
            ]);
        }
        $product->load(['user', 'images', 'categories']);
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $product->delete();

        return response()->json([
            'message' => 'Prodotto eliminato correttamente.'
        ]);
    }
    
    // Recupero i prodotti collegati all'utente
    public function myProducts()
    {
        $products = auth()->user()->products()->with(['categories','images'])->latest()->get();
        return response()->json($products);
    }
}
