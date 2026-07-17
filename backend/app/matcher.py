import json
import ast
import pandas as pd
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import warnings

# Kütüphanelerin iç bilgilendirme ve token_pattern uyarılarını tamamen gizler
warnings.filterwarnings("ignore", category=UserWarning) 

def load_career_roles(json_path='data/roles/bootcamp_roles.json'):
    """Buse'nin hazırladığı hedef meslek ve yetenek şablonunu okur."""
    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            return json.load(f)['roles']
    except Exception as e:
        print(f"JSON dosyası okunurken hata: {e}")
        return []

def calculate_role_matching(user_skills_list, json_path='data/roles/bootcamp_roles.json'):
    """
    1. ADIM: Kullanıcının yeteneklerini Buse'nin JSON dosyasındaki 
    standart meslek kriterleriyle kıyaslar. Radar ve SWOT analizini besler.
    """
    roles = load_career_roles(json_path)
    user_skills_lower = [s.lower() for s in user_skills_list]
    results = []
    
    for role in roles:
        role_title = role['title']
        required_skills = role['required_skills']
        role_skills_lower = [skill['name'].lower() for skill in required_skills]
        
        if not user_skills_lower:
            match_percentage = 0.0
        else:
            # Vektörizatörü katı virgül ayrımından kurtarıp, kelime tabanlı NLP uzayına çekiyoruz
            vectorizer = CountVectorizer(lowercase=True)
            vectors = vectorizer.fit_transform([' '.join(role_skills_lower), ' '.join(user_skills_lower)])
            match_percentage = float(cosine_similarity(vectors[0], vectors[1])[0][0] * 100)
        
        # Arayüzdeki güçlü ve zayıf yönleri kısmi eşleşme (partial match) ile dinamik yakalar
        matched_skills = []
        missing_skills = []
        
        for s in required_skills:
            req_skill = s['name'].lower()
            # Eğer kullanıcının yeteneği hedef yeteneğin içinde geçiyorsa (veya tam tersi) eşleşme say!
            if any(u in req_skill or req_skill in u for u in user_skills_lower):
                matched_skills.append(s['name'])
            else:
                missing_skills.append(s['name'])
        
        if match_percentage >= 70:
            status_bucket = "A - HAZIR"
        elif match_percentage >= 40:
            status_bucket = "B - YAKIN"
        else:
            status_bucket = "C - ULAŞILABİLİR"
            
        # DİKKAT: Append işlemi if-else bloğunun DIŞINA alındı! Artık yüksek skorlar da listeye girecek.
        results.append({
            "title": role_title,
            "match_percentage": round(match_percentage, 1),
            "status_bucket": status_bucket,
            "strengths": matched_skills if matched_skills else ["Temel Bilgiler"],
            "weaknesses": missing_skills if missing_skills else ["Eksik yok"]
        })
        
    return sorted(results, key=lambda x: x['match_percentage'], reverse=True)


def find_best_matches_from_big_data(user_skills_list, csv_path='data/roles/first_dataset_cleaned.csv'):
    """
    2. ADIM: Senin temizlediğin 9404 satırlık büyük veri setini kullanan fonksiyon.
    Yüzdelik skorlar virgülden sonra iki basamağa yuvarlanarak temiz çıktı üretir.
    """
    try:
        df = pd.read_csv(csv_path)
        
        def safe_convert_to_str(x):
            if pd.isna(x):
                return ''
            if isinstance(x, str):
                try:
                    actual_list = ast.literal_eval(x)
                    return ' '.join(actual_list)
                except:
                    cleaned = x.replace('[', '').replace(']', '').replace("'", "").replace('"', '').replace(',', ' ')
                    return cleaned
            return ''

        df['skills_str'] = df['skills'].apply(safe_convert_to_str)
        user_skills_str = ' '.join(user_skills_list)
        
        # İngilizce stop-word'leri (and, or, the vs.) çıkararak Kaggle verisindeki alakasız eşleşmeleri önlüyoruz
        vectorizer = CountVectorizer(stop_words='english', lowercase=True)
        all_skills_vectors = vectorizer.fit_transform(df['skills_str'].tolist() + [user_skills_str])
        
        dataset_vectors = all_skills_vectors[:-1]
        user_vector = all_skills_vectors[-1]
        
        similarity_scores = cosine_similarity(user_vector, dataset_vectors).flatten()
        df['match_percentage'] = similarity_scores * 100
        
        # En yüksek uyumlu 3 farklı pozisyonu seç
        top_matches = df.sort_values(by='match_percentage', ascending=False).drop_duplicates(subset=['job_position_name']).head(3)
        
        # Çıktıları virgülden sonra 2 basamağa yuvarlayarak dict formatına çeviriyoruz
        result_dict = []
        for _, row in top_matches.iterrows():
            result_dict.append({
                "job_position_name": row['job_position_name'],
                "match_percentage": round(row['match_percentage'], 2)
            })
        return result_dict
        
    except Exception as e:
        print(f"Büyük veri setinde eşleşme hesaplanırken hata: {e}")
        return []

if __name__ == "__main__":
    test_yetenekler = ['Python', 'SQL', 'Excel', 'Pandas', 'İletişim']
    json_yolu = 'data/roles/bootcamp_roles.json'
    csv_yolu = 'data/roles/first_dataset_cleaned.csv'
    
    print("1. Standart Rol Eşleşmeleri (JSON'dan):")
    print(calculate_role_matching(test_yetenekler, json_path=json_yolu))
    
    print("\n2. Büyük Veri Havuzundan En Uygun Pozisyonlar (Senin Temizlediğin CSV'den):")
    print(find_best_matches_from_big_data(test_yetenekler, csv_path=csv_yolu))